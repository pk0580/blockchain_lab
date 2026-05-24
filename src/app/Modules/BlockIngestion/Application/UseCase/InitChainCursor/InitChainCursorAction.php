<?php

declare(strict_types=1);

namespace App\Modules\BlockIngestion\Application\UseCase\InitChainCursor;

use App\Modules\BlockIngestion\Domain\Contract\BlockSourceFactory;
use App\Modules\BlockIngestion\Domain\Entity\ScanCursor;
use App\Modules\BlockIngestion\Domain\Repository\ScanCursorRepository;
use App\Modules\Network\Domain\Exception\ChainNotFoundException;
use App\Modules\Network\Domain\Repository\ChainRepository;
use App\Modules\Network\Domain\ValueObject\ChainId;
use DateTimeImmutable;
use Illuminate\Database\DatabaseManager;

/**
 * Idempotent. If a cursor already exists for the chain we leave it alone —
 * re-running this command must never roll the scanner backwards.
 */
final readonly class InitChainCursorAction
{
    public function __construct(
        private ChainRepository $chains,
        private ScanCursorRepository $cursors,
        private BlockSourceFactory $sources,
        private DatabaseManager $db,
    ) {}

    public function handle(InitChainCursorData $data): ScanCursor
    {
        $chainId = new ChainId($data->chainId);
        $chain = $this->chains->findById($chainId)
            ?? throw ChainNotFoundException::byId($chainId);

        $existing = $this->cursors->findByChain($chainId);
        if ($existing !== null) {
            return $existing;
        }

        $head = $this->sources->for($chain)->currentHead();

        $cursor = ScanCursor::initialise($chainId, $head, new DateTimeImmutable());
        $this->db->transaction(function () use ($cursor): void {
            $this->cursors->save($cursor);
        });

        return $cursor;
    }
}
