# Domain Layer

Pure PHP. No framework. No I/O.

---

## What Goes Here

Each bounded context has the following subfolders under `{APP_ROOT}/app/Domain/{Ctx}/`:

| Subfolder | Contents | Example |
|---|---|---|
| `Entity/` | Aggregate roots and entities with identity | `Entity/Order.php` |
| `ValueObject/` | Immutable, equality by value | `ValueObject/Money.php`, `ValueObject/OrderId.php` |
| `Repository/` | Persistence contracts (interfaces only) | `Repository/OrderRepository.php` |
| `Event/` | Past-tense domain events, ids only | `Event/OrderPaid.php` |
| `Exception/` | Business-meaningful failures, named after the violated invariant | `Exception/InvalidOrderStatusException.php` |
| `Service/` (optional, rare) | Stateless domain services that span entities | `Service/PriceCalculator.php` |

## What Does Not Go Here

- `Illuminate\*`, `Eloquent`, `Request`, `DB`, `Cache`, `Queue`, `Event`, `Log`, `Str`, `Arr` (unless the project ships a framework-free fork), `carbon` (use `DateTimeImmutable`)
- HTTP, JSON, serialization annotations
- Database-specific types
- `ShouldQueue` and other Laravel contracts

---

## Invariants

Enforce invariants in the constructor and in every mutating method. Never trust callers.

```php
// {APP_ROOT}/app/Domain/Customer/ValueObject/Email.php
namespace App\Domain\Customer\ValueObject;

final readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email: $value");
        }
    }
}

// {APP_ROOT}/app/Domain/Order/ValueObject/Money.php
namespace App\Domain\Order\ValueObject;

final readonly class Money
{
    public function __construct(
        public int $amountCents,
        public string $currency,
    ) {
        if ($amountCents < 0) {
            throw new InvalidArgumentException('Money cannot be negative');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException("Invalid ISO 4217 currency: $currency");
        }
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new DomainException('Currency mismatch');
        }
        return new self($this->amountCents + $other->amountCents, $this->currency);
    }
}
```

## Entities

- Private constructor + named constructor (`Order::create(...)`, `Order::reconstitute(...)`).
- No public mutable state. Mutations go through methods that express business intent.
- Expose read-only properties or explicit accessors.
- Do not implement `toArray()`; mapping to persistence or JSON is done outside.

```php
// {APP_ROOT}/app/Domain/Order/Entity/Order.php
namespace App\Domain\Order\Entity;

use App\Domain\Order\ValueObject\{OrderId, CustomerId, OrderStatus};
use App\Domain\Order\Exception\InvalidOrderStatusException;

final class Order
{
    private function __construct(
        public readonly OrderId $id,
        public readonly CustomerId $customerId,
        private OrderStatus $status,
        private array $items,     // list<OrderItem>
    ) {}

    public static function create(CustomerId $customerId): self
    {
        return new self(
            OrderId::generate(),
            $customerId,
            OrderStatus::Draft,
            [],
        );
    }

    public static function reconstitute(
        OrderId $id,
        CustomerId $customerId,
        OrderStatus $status,
        array $items,
    ): self {
        return new self($id, $customerId, $status, $items);
    }

    public function markAsPaid(): void
    {
        if ($this->status !== OrderStatus::Confirmed) {
            throw new InvalidOrderStatusException("Cannot pay order in {$this->status->value}");
        }
        $this->status = OrderStatus::Paid;
    }
}
```

## Value Objects

- `readonly` classes (PHP 8.2+) or individually-readonly properties.
- Equality by value: implement `equals(self $other): bool`.
- No setters. "Mutation" returns a new instance (`Money::add()`).

## Domain Events

- Past tense, immutable, carry ids and minimal data — not full entities.
- Raised by entities or Actions, dispatched by Application layer after successful write.

```php
// {APP_ROOT}/app/Domain/Order/Event/OrderPaid.php
namespace App\Domain\Order\Event;

use App\Domain\Order\ValueObject\OrderId;

final readonly class OrderPaid
{
    public function __construct(
        public OrderId $orderId,
        public DateTimeImmutable $paidAt,
    ) {}
}
```

## Repository Interfaces

Defined in Domain, implemented in Infrastructure.

```php
// {APP_ROOT}/app/Domain/Order/Repository/OrderRepository.php
namespace App\Domain\Order\Repository;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\ValueObject\OrderId;

interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
    public function nextId(): OrderId;
}
```

Keep the interface narrow. Read-heavy queries belong in a separate read-side repository or query object.

## Testing

Domain is the easiest thing to test: fast, no framework, deterministic.

```php
it('cannot pay a draft order', function () {
    $order = Order::create(new CustomerId('c-1'));
    expect(fn () => $order->markAsPaid())
        ->toThrow(InvalidOrderStatusException::class);
});
```
