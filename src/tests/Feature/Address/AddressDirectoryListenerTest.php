<?php

declare(strict_types=1);

namespace Tests\Feature\Address;

use App\Modules\Address\Domain\Event\AddressGenerated;
use App\Modules\Address\Domain\ValueObject\AddressId;
use App\Modules\Address\Domain\ValueObject\HdSeedId;
use App\Modules\BlockIngestion\Domain\Contract\AddressDirectory;
use App\Modules\Network\Domain\ValueObject\ChainFamily;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;

it('listener writes every generated address into the directory', function (): void {
    /** @var Dispatcher $events */
    $events = app(Dispatcher::class);

    $events->dispatch(new AddressGenerated(
        addressId: new AddressId('11111111-2222-3333-4444-555555555555'),
        seedId: new HdSeedId('22222222-3333-4444-5555-666666666666'),
        family: ChainFamily::Bitcoin,
        address: 'bcrt1q-listener-test',
        derivationPath: "m/44'/0'/0'/0/0",
        occurredAt: new DateTimeImmutable(),
    ));

    /** @var AddressDirectory $directory */
    $directory = app(AddressDirectory::class);
    expect($directory->isWatched(ChainFamily::Bitcoin, 'bcrt1q-listener-test'))->toBeTrue();
    expect($directory->isWatched(ChainFamily::Evm, 'bcrt1q-listener-test'))->toBeFalse();
});
