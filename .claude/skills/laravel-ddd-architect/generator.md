# Laravel Structure Generator

Generates a real Laravel folder structure based on Clean Architecture
and DDD, scaled to the chosen complexity tier.

---

## Base Structures

### Layer-First (Small / Medium projects)

```
{APP_ROOT}/app/
├── Domain/
│   └── {BoundedContext}/
│       ├── Entity/
│       ├── ValueObject/
│       ├── Repository/         (interfaces)
│       ├── Event/
│       └── Exception/
│
├── Application/
│   └── {BoundedContext}/
│       ├── UseCase/{Verb}{Noun}/
│       │   ├── {Verb}{Noun}Action.php
│       │   └── {Verb}{Noun}Data.php
│       ├── DTO/
│       ├── Command/
│       └── Query/
│
├── Infrastructure/
│   └── {BoundedContext}/
│       ├── Persistence/Eloquent/
│       │   ├── Models/
│       │   ├── Repositories/
│       │   └── Mappers/
│       ├── Event/Listener/
│       ├── Job/
│       └── Provider/
│
└── Interface/
    └── Http/
        └── {BoundedContext}/
            ├── Controller/
            ├── Request/
            ├── Resource/
            └── Policy/
```

### Domain-First Modules (Large projects)

```
{APP_ROOT}/app/
└── Modules/
    └── {BoundedContext}/
        ├── Domain/
        ├── Application/
        ├── Infrastructure/
        └── UI/
```

---

## Complete Example: `Order` Module (Complex tier)

```
{APP_ROOT}/app/
├── Domain/Order/
│   ├── Entity/Order.php
│   ├── ValueObject/OrderId.php
│   ├── ValueObject/OrderStatus.php
│   ├── ValueObject/Money.php
│   ├── Repository/OrderRepository.php
│   ├── Event/OrderCreated.php
│   ├── Event/OrderPaid.php
│   └── Exception/InvalidOrderStatusException.php
│
├── Application/Order/
│   ├── UseCase/CreateOrder/
│   │   ├── CreateOrderAction.php
│   │   └── CreateOrderData.php
│   ├── UseCase/PayOrder/
│   │   ├── PayOrderAction.php
│   │   └── PayOrderData.php
│   └── Query/GetOrderDashboard/
│       ├── GetOrderDashboardQuery.php
│       ├── GetOrderDashboardHandler.php
│       └── DashboardView.php
│
├── Infrastructure/Order/
│   ├── Persistence/Eloquent/Models/OrderModel.php
│   ├── Persistence/Eloquent/Repositories/EloquentOrderRepository.php
│   ├── Persistence/Eloquent/Mappers/OrderMapper.php
│   ├── Event/Listener/SendReceiptOnOrderPaid.php
│   ├── Job/ProcessOrderPaymentJob.php
│   └── Provider/OrderServiceProvider.php
│
└── Interface/Http/Order/
    ├── Controller/CreateOrderController.php
    ├── Controller/PayOrderController.php
    ├── Request/CreateOrderRequest.php
    ├── Request/PayOrderRequest.php
    ├── Resource/OrderResource.php
    └── Policy/OrderPolicy.php

{APP_ROOT}/tests/
├── Unit/Domain/Order/OrderTest.php
├── Unit/Domain/Order/MoneyTest.php
├── Feature/Order/CreateOrderTest.php
├── Integration/Infrastructure/Order/EloquentOrderRepositoryTest.php
└── Architecture/OrderArchTest.php
```

---

## Namespace Rules

| Layer | Namespace |
|---|---|
| Domain | `App\Domain\{BoundedContext}\...` |
| Application | `App\Application\{BoundedContext}\...` |
| Infrastructure | `App\Infrastructure\{BoundedContext}\...` |
| Interface | `App\Interface\Http\{BoundedContext}\...` |
| Module variant | `App\Modules\{BoundedContext}\{Layer}\...` |

PSR-4 autoload path matches namespace exactly.

---

## File Mapping

| Artifact | Path |
|---|---|
| Entity / Aggregate | `{APP_ROOT}/app/Domain/{Ctx}/Entity/` |
| Value Object | `{APP_ROOT}/app/Domain/{Ctx}/ValueObject/` |
| Repository interface | `{APP_ROOT}/app/Domain/{Ctx}/Repository/` |
| Domain Event | `{APP_ROOT}/app/Domain/{Ctx}/Event/` |
| Domain Exception | `{APP_ROOT}/app/Domain/{Ctx}/Exception/` |
| UseCase / Action | `{APP_ROOT}/app/Application/{Ctx}/UseCase/{Verb}{Noun}/` |
| DTO | `{APP_ROOT}/app/Application/{Ctx}/UseCase/{Verb}{Noun}/` (co-located with use case) |
| Command / Query | `{APP_ROOT}/app/Application/{Ctx}/Command|Query/` |
| Eloquent Model | `{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Models/` |
| Eloquent Repository | `{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Repositories/` |
| Mapper | `{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Mappers/` |
| Listener | `{APP_ROOT}/app/Infrastructure/{Ctx}/Event/Listener/` |
| Job | `{APP_ROOT}/app/Infrastructure/{Ctx}/Job/` |
| Service Provider | `{APP_ROOT}/app/Infrastructure/{Ctx}/Provider/` |
| Controller | `{APP_ROOT}/app/Interface/Http/{Ctx}/Controller/` |
| Form Request | `{APP_ROOT}/app/Interface/Http/{Ctx}/Request/` |
| API Resource | `{APP_ROOT}/app/Interface/Http/{Ctx}/Resource/` |
| Policy | `{APP_ROOT}/app/Interface/Http/{Ctx}/Policy/` |

---

## CQRS-Lite Add-Ons

```
{APP_ROOT}/app/Application/{Ctx}/
├── Query/{Noun}/
│   ├── Get{Noun}Query.php
│   ├── Get{Noun}Handler.php
│   └── {Noun}View.php
└── ReadModel/   (optional, if read-side needs separate models)

{APP_ROOT}/app/Infrastructure/{Ctx}/Persistence/Eloquent/Repositories/
└── Eloquent{Noun}ReadRepository.php
```

---

## High-Load Add-Ons

```
{APP_ROOT}/app/Infrastructure/{Ctx}/
├── Cache/
├── Queue/
├── Outbox/
│   ├── OutboxMessage.php           (Eloquent model)
│   └── PublishOutboxJob.php
└── ReadModel/
```

---

## Output Format

For Complex features, the response shows:

1. **Folder tree** (text, scoped to the new files)
2. **File-by-file generation**, each with the full path on its own
   line above the code block
3. **Service provider binding** snippet
4. **Route registration** snippet
5. **Test names** to be written

---

## Strict Rules

- NEVER mix layers
- NEVER put Eloquent outside Infrastructure (Medium / Complex tier)
- NEVER reach into another module's Domain — go through public Actions
  or events
- ALWAYS follow PSR-4
- ALWAYS use `final` and `declare(strict_types=1);`
- ALWAYS provide a Mapper between Eloquent and Domain in Complex tier
