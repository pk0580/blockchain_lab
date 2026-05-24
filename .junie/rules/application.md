# Application Layer

Orchestrate one use case per class. Depend on Domain and on infrastructure interfaces; never on HTTP, Eloquent, or Blade.

---

## What Goes Here

- **Actions** (use cases) — one public entry point, one responsibility.
- **Commands / Queries** (CQRS flavor) — intent objects paired with handlers when the project uses a command bus.
- **DTOs** — transport shapes for input (`CreateOrderData`) and output (`OrderView`).
- **Input ports** for infrastructure — interfaces like `PaymentGateway`, `MailSender` when the project wants to invert dependencies on concrete Laravel features.

## What Does Not Go Here

- HTTP (`Request`, `Response`) — Controllers translate HTTP to DTO.
- Eloquent models or queries — Infrastructure encapsulates those.
- Blade, resources, or serialization.
- Domain rules — belong in entities or value objects.

---

## Action Structure

```php
// {APP_ROOT}/app/Application/Order/UseCase/CreateOrder/CreateOrderAction.php
namespace App\Application\Order\UseCase\CreateOrder;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\Event\OrderCreated;
use App\Domain\Order\Repository\OrderRepository;
use App\Domain\Order\ValueObject\{CustomerId, Money, OrderId, Sku};
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

final readonly class CreateOrderAction
{
    public function __construct(
        private OrderRepository $orders,
        private Dispatcher $events,
        private DatabaseManager $db,
    ) {}

    public function handle(CreateOrderData $data): OrderId
    {
        return $this->db->transaction(function () use ($data) {
            $order = Order::create(new CustomerId($data->customerId));
            foreach ($data->items as $item) {
                $order->addItem(new Sku($item->sku), $item->quantity, new Money($item->priceCents, 'USD'));
            }
            $this->orders->save($order);
            $this->events->dispatch(new OrderCreated($order->id));
            return $order->id;
        });
    }
}
```

Rules:

- One public method: `handle()` (preferred) or `__invoke()`. Pick one style per project.
- Constructor-injected dependencies only.
- `readonly` class where all dependencies are injected at construction.
- Wrap writes in `DB::transaction()` when they touch more than one row or dispatch follow-up events.
- Dispatch domain events with `DB::afterCommit(...)` if handlers must not run when the transaction rolls back.

## Transactions

- Application starts the transaction. Domain entities do not know about transactions.
- One transaction per use case. Do not nest unless composing sub-use-cases deliberately.

## DTOs

Prefer `readonly` classes with typed properties. Use `spatie/laravel-data` when the DTO must map to HTTP forms or JSON with validation.

```php
// {APP_ROOT}/app/Application/Order/UseCase/CreateOrder/CreateOrderData.php — co-located with the action
namespace App\Application\Order\UseCase\CreateOrder;

use App\Interface\Http\Order\Request\CreateOrderRequest;

final readonly class CreateOrderData
{
    /** @param list<CreateOrderItemData> $items */
    public function __construct(
        public string $customerId,
        public array $items,
    ) {}

    public static function fromRequest(CreateOrderRequest $request): self
    {
        return new self(
            customerId: $request->validated('customer_id'),
            items: array_map(
                fn (array $i) => new CreateOrderItemData($i['sku'], $i['qty'], $i['price_cents']),
                $request->validated('items'),
            ),
        );
    }
}
```

## Commands and Queries (optional)

Adopt CQRS when reads and writes diverge enough to justify two models. Otherwise, plain Actions suffice. See `rules/advanced_patterns.md` (CQRS section).

## Errors

- Catch Domain exceptions at the Action boundary only if the HTTP response needs a specific mapping; otherwise let them propagate to the Form Request exception renderer.
- Wrap infrastructure exceptions in Application-level exceptions when the caller should not know about Stripe or PostgreSQL specifics.

## Return Types

- Actions return:
  - A Domain entity or VO (`OrderId`, `Customer`) for internal callers.
  - A DTO (`OrderView`) for UI-ready shapes.
  - `void` for fire-and-forget (rare; prefer an id).
- Never return Eloquent models or arrays of Eloquent models.

## Testing

Actions get feature-level tests that boot Laravel (so the container can wire dependencies) plus pure unit tests for Domain behavior.

```php
it('creates an order with items', function () {
    $action = app(CreateOrderAction::class);
    $id = $action->handle(new CreateOrderData('cust-1', [new CreateOrderItemData('sku-1', 2, 999)]));

    expect($id)->toBeInstanceOf(OrderId::class);
    Event::assertDispatched(OrderCreated::class);
});
```
