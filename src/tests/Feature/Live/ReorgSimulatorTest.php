<?php

declare(strict_types=1);

namespace Tests\Feature\Live;

use App\Modules\BlockIngestion\Application\UseCase\InitChainCursor\InitChainCursorAction;
use App\Modules\BlockIngestion\Application\UseCase\InitChainCursor\InitChainCursorData;
use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockAction;
use App\Modules\BlockIngestion\Application\UseCase\ScanNextBlock\ScanNextBlockData;
use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\BlockIngestion\Domain\Repository\IncomingTransactionRepository;
use App\Modules\BlockIngestion\Domain\ValueObject\IncomingTxStatus;
use App\Modules\BlockIngestion\Infrastructure\BlockSource\BitcoinRpcClient;
use App\Modules\Confirmation\Application\UseCase\UpdateConfirmations\UpdateConfirmationsAction;
use App\Modules\Confirmation\Application\UseCase\UpdateConfirmations\UpdateConfirmationsData;
use App\Modules\Ledger\Domain\Repository\LedgerEntryRepository;
use App\Modules\Ledger\Domain\ValueObject\Direction;
use App\Modules\Ledger\Domain\ValueObject\EntryStatus;
use App\Modules\Ledger\Domain\ValueObject\WalletId;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainAction;
use App\Modules\Network\Application\UseCase\RegisterChain\RegisterChainData;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use App\Modules\Network\Domain\ValueObject\ChainId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

uses(RefreshDatabase::class);

/**
 * Полный сценарий reorg против живого bitcoind regtest. По умолчанию пропускается.
 * Чтобы запустить: запустить `bitcoin-regtest` контейнер и установить
 * `BITCOIN_LIVE_TESTS=1` в окружении (или в phpunit.xml).
 *
 * Сценарий ровно тот, что описан в STEPS.md §6:
 *   1. Mine 101 → coinbase созревает.
 *   2. sendtoaddress на наш «watched» адрес.
 *   3. Mine 6 → tx достигает confirmed.
 *   4. Сканер: detect → confirming → confirmed → Ledger credit.
 *   5. invalidateblock на блок с tx → mine 7 на новой ветке.
 *   6. Итеративно сканируем → ReorgDetection orphan'ит блоки → Ledger reversal.
 */
function liveRpc(): BitcoinRpcClient
{
    return new BitcoinRpcClient(
        http: app(HttpFactory::class),
        url: (string) env('BITCOIN_RPC_URL', 'http://bitcoin-regtest:18443'),
        user: (string) env('BITCOIN_RPC_USER', 'bitcoin'),
        password: (string) env('BITCOIN_RPC_PASSWORD', 'bitcoin'),
        timeoutSeconds: 5,
    );
}

function liveBitcoindAvailable(): bool
{
    if ((string) env('BITCOIN_LIVE_TESTS') !== '1') {
        return false;
    }
    try {
        liveRpc()->getBlockCount();
        return true;
    } catch (Throwable) {
        return false;
    }
}

function ensureLiveTestWallet(BitcoinRpcClient $rpc, string $name): void
{
    try {
        $rpc->call('createwallet', [$name]);
    } catch (Throwable) {
        // wallet already loaded or exists — fine for our purposes.
    }
}

function scanUntilCaughtUp(string $chainId, int $maxTicks = 50): void
{
    for ($i = 0; $i < $maxTicks; $i++) {
        $result = app(ScanNextBlockAction::class)->handle(new ScanNextBlockData($chainId));
        if ($result->blocksScanned === 0) {
            return;
        }
    }
}

it('compensates a deposit when its block is orphaned', function (): void {
    if (! liveBitcoindAvailable()) {
        test()->markTestSkipped(
            'BITCOIN_LIVE_TESTS != 1 или bitcoin-regtest RPC недоступен. '
            .'Поднимите контейнер и выставьте env, чтобы пройти этот сценарий.'
        );
    }

    $rpc = liveRpc();
    $walletName = 'phase5-reorg-'.bin2hex(random_bytes(4));
    ensureLiveTestWallet($rpc, $walletName);

    // Bitcoin Core ≥ 0.21 требует указывать кошелёк в URL.
    $walletRpcUrl = rtrim((string) env('BITCOIN_RPC_URL'), '/').'/wallet/'.$walletName;
    $walletRpc = new BitcoinRpcClient(
        http: app(HttpFactory::class),
        url: $walletRpcUrl,
        user: (string) env('BITCOIN_RPC_USER', 'bitcoin'),
        password: (string) env('BITCOIN_RPC_PASSWORD', 'bitcoin'),
        timeoutSeconds: 10,
    );

    $coinbaseAddr = (string) $walletRpc->call('getnewaddress', []);
    $ourAddr = (string) $walletRpc->call('getnewaddress', []);

    // Регистрируем сеть с короткими параметрами, чтобы сценарий укладывался в тест.
    app(RegisterChainAction::class)->handle(new RegisterChainData(
        chainId: 'bitcoin-regtest',
        name: 'Bitcoin Regtest',
        family: ChainFamily::Bitcoin->value,
        currencySymbol: 'BTC',
        currencyDecimals: 8,
        requiredConfirmations: 3,
        maxReorgDepth: 50,
        endpoints: [['url' => (string) env('BITCOIN_RPC_URL'), 'kind' => 'http']],
    ));

    // Адрес → wallet_id для Ledger owner-резолва.
    $walletUuid = Str::uuid()->toString();
    $seedId = Str::uuid()->toString();
    DB::table('hd_seeds')->insert([
        'id' => $seedId,
        'reference' => 'live-'.substr($seedId, 0, 8),
        'family' => ChainFamily::Bitcoin->value,
        'created_at' => '2026-05-22 10:00:00+00',
    ]);
    DB::table('addresses')->insert([
        'id' => Str::uuid()->toString(),
        'hd_seed_id' => $seedId,
        'family' => ChainFamily::Bitcoin->value,
        'address' => $ourAddr,
        'derivation_path' => "m/44'/0'/0'/0/0",
        'derivation_index' => 0,
        'wallet_id' => $walletUuid,
        'created_at' => '2026-05-22 10:00:00+00',
    ]);
    app(AddressDirectory::class)->register(ChainFamily::Bitcoin, $ourAddr);

    // Mine 101 → coinbase созревает.
    $walletRpc->call('generatetoaddress', [101, $coinbaseAddr]);

    // Sendtoaddress + ещё несколько блоков для confirmations.
    $walletRpc->call('sendtoaddress', [$ourAddr, 0.5]);
    $walletRpc->call('generatetoaddress', [3, $coinbaseAddr]);

    app(InitChainCursorAction::class)->handle(new InitChainCursorData('bitcoin-regtest'));
    // initialise ставит cursor на head; чтобы дойти до tx, перематываем в 0.
    DB::table('scan_cursors')->where('chain_id', 'bitcoin-regtest')->update([
        'last_scanned_height' => 0,
    ]);
    scanUntilCaughtUp('bitcoin-regtest');
    app(UpdateConfirmationsAction::class)->handle(new UpdateConfirmationsData('bitcoin-regtest'));

    /** @var IncomingTransactionRepository $incoming */
    $incoming = app(IncomingTransactionRepository::class);
    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    expect($rows)->not->toBeEmpty();
    $depositRow = $rows[0];
    expect($depositRow->status())->toBe(IncomingTxStatus::Confirmed);

    /** @var LedgerEntryRepository $ledger */
    $ledger = app(LedgerEntryRepository::class);
    $entries = $ledger->listByWallet(new WalletId($walletUuid));
    expect($entries)->toHaveCount(1);
    expect($entries[0]->direction)->toBe(Direction::Credit);
    expect($entries[0]->status())->toBe(EntryStatus::Confirmed);

    // Reorg: invalidateblock на высоте депозита + mine 7 новых блоков альтернативной ветки.
    $depositHeight = $depositRow->blockHeight?->value;
    expect($depositHeight)->not->toBeNull();
    $depositBlockHash = $rpc->getBlockHash($depositHeight);
    $rpc->call('invalidateblock', [$depositBlockHash]);
    $walletRpc->call('generatetoaddress', [7, $coinbaseAddr]);

    // Итеративные тики сканера; ReorgDetection orphan'ит блоки по одному.
    scanUntilCaughtUp('bitcoin-regtest');
    app(UpdateConfirmationsAction::class)->handle(new UpdateConfirmationsData('bitcoin-regtest'));

    $rows = $incoming->findByChain(new ChainId('bitcoin-regtest'));
    $orphaned = collect($rows)->firstWhere(fn ($r) => $r->id->value === $depositRow->id->value);
    expect($orphaned?->status())->toBe(IncomingTxStatus::Orphaned);

    $entries = $ledger->listByWallet(new WalletId($walletUuid));
    expect($entries)->toHaveCount(2);
    $credit = collect($entries)->firstWhere(fn ($e) => $e->direction === Direction::Credit);
    $debit = collect($entries)->firstWhere(fn ($e) => $e->direction === Direction::Debit);
    expect($credit?->status())->toBe(EntryStatus::Reversed);
    expect($debit?->status())->toBe(EntryStatus::Confirmed);
});
