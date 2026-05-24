# DSL — Domain Definition Language

A minimal shorthand to describe a domain so the skill can generate
the full structure without prose back-and-forth.

---

## Example

```
aggregate Order {
    id:         OrderId
    customerId: CustomerId
    status:     OrderStatus
    items:      list<OrderItem>

    behavior:
        create(customerId: CustomerId)
        addItem(sku: Sku, qty: int, price: Money)
        confirm()
        markAsPaid()
        cancel(reason: string)

    invariants:
        items.size > 0 when confirm
        status == Confirmed when markAsPaid

    events:
        OrderCreated(orderId, customerId)
        OrderPaid(orderId, paidAt)
        OrderCancelled(orderId, reason)
}
```

---

## Supported Constructs

### `aggregate`

Defines an aggregate root entity with identity, mutable state hidden
behind behavior methods, and lifecycle events.

### `value-object`

```
value-object Money {
    amountCents: int
    currency:    string

    invariants:
        amountCents >= 0
        currency matches /^[A-Z]{3}$/

    operations:
        add(other: Money): Money
        equals(other: Money): bool
}
```

### `use-case`

```
use-case CreateOrder {
    input:  customerId: string, items: list<{sku: string, qty: int, price: int}>
    output: orderId
    side-effects:
        - dispatch OrderCreated
}
```

### `query`

```
query GetCustomerDashboard {
    input:  customerId: string
    output: DashboardView
    source: read-side projection
}
```

### `repository`

```
repository OrderRepository {
    save(order: Order): void
    findById(id: OrderId): Order?
    nextId(): OrderId
}
```

---

## Generation Rules

- `aggregate` → Entity (Domain) + private constructor + named
  constructors + intent methods
- `value-object` → `readonly class` (Domain) with invariants in
  constructor, `equals(self $other): bool` method, and any declared
  `operations`
- `use-case` → DTO (Application) + Action / UseCase (Application)
- `query` → Query DTO + read repository method + read-side DTO
- `repository` → interface in Domain + Eloquent impl in Infrastructure
  + Mapper

---

## Goal

Reduce ceremony. Describe intent, get the scaffolding.
