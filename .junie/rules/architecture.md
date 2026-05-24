# Workflow

Every task runs in one of four modes. Detect the mode from the user's intent before writing code.

---

## Modes

| Mode | Trigger words | Primary goal |
|---|---|---|
| `FEATURE` | add, implement, build, create | Deliver new behavior behind a clean boundary |
| `FIX` | bug, broken, fails, wrong, crash | Restore expected behavior with minimal diff |
| `REFACTOR` | refactor, clean up, rename, extract, simplify | Change shape without changing behavior |
| `TEST` | add tests, cover, missing tests, increase coverage | Raise test confidence |

If intent is ambiguous, ask once; otherwise default to `FEATURE`.

---

## Pipelines

### FEATURE

1. **ARCHITECT** — pick complexity tier, layer boundaries, and the module's directory layout. Announce the decision in the response header.
2. **IMPLEMENT** — write code per the chosen templates. No speculative abstraction.
3. **SELF-REVIEW** — run the checklist in `rules/quality_gate.md`. List issues or write `OK`.
4. **QA** — add or extend tests; describe what is covered.

### FIX

1. **REPRODUCE** — describe the failing path in one or two sentences. If you cannot reproduce, say so explicitly.
2. **IMPLEMENT** — change the smallest amount of code that addresses the root cause. No drive-by edits. Do not rename, reformat, or restructure unrelated code.
3. **SELF-REVIEW** — verify the fix does not introduce regressions elsewhere. Add a regression test.

### REFACTOR

1. **PLAN** — list the moves. Keep public APIs stable unless the task is the rename.
2. **IMPLEMENT** — mechanical changes; no behavior changes.
3. **SELF-REVIEW** — confirm tests are untouched or only moved. All green.

### TEST

1. **QA** — identify untested paths; add feature tests first, then unit tests.

---

## Self-Review Loop

After IMPLEMENT, run SELF-REVIEW. If it reports issues, apply FIX, then SELF-REVIEW again. Maximum 3 loops; stop on `OK`.

Do not ship code with unresolved SELF-REVIEW issues. If you cannot resolve them in 3 loops, surface the blocker and stop.

---

## Constraints

- Prefer the simplest solution that meets the requirements.
- Fix root causes. Do not mute errors, skip tests, or suppress warnings to make a check pass.
- Do not introduce TODOs for the reviewer. Either do it now or leave it out and explain why.
- Do not bundle unrelated changes into a FIX. They belong in a follow-up REFACTOR.
# Output Format

Every response that produces code must start with a header line identifying mode, complexity, and architecture tier.

```
[MODE] [COMPLEXITY] [ARCH]
```

Examples:

- `[FEATURE] [MEDIUM] [Action+DTO]`
- `[FIX] [SIMPLE] [CRUD]`
- `[REFACTOR] [COMPLEX] [DDD]`

---

## Sections

Follow this structure. Omit a section if it is not relevant to the task.

```
[MODE] [COMPLEXITY] [ARCH]

Brief rationale: 1–2 sentences on why this tier was chosen.

--- IMPLEMENTATION ---
<code blocks, grouped by file, with the full file path as the block header>

--- SELF-REVIEW ---
<at most 5 issues, or the single word: OK>

--- FIX ---
<only if SELF-REVIEW found issues; show the changed portions only>

--- QA ---
<tests added or modified, with test names and what they cover>
```

---

## Code Blocks

- One block per file.
- Precede each block with the file path on its own line.
- `declare(strict_types=1);` at the top of every PHP file, immediately after the opening `<?php` tag.
- Omit unchanged portions when fixing; show context of at most 3 lines around the change.
- Do not repeat the same file twice in one response; combine edits.

Example:

```
// {APP_ROOT}/app/Application/Order/UseCase/CreateOrder/CreateOrderAction.php
final readonly class CreateOrderAction { ... }
```

---

## Prose

- Prefer lists over paragraphs.
- No marketing language ("robust", "seamless", "powerful").
- No restating the user's question back to them.
- Mention trade-offs the reviewer should know about (e.g., "chose Medium over Complex because there is no state machine yet; revisit if a `payment_failed` state is added").

---

## When You Cannot Complete the Task

State clearly:

1. What you tried.
2. What blocked you.
3. What you need from the user to continue.

Do not ship partial code with `TODO`s to hide an incomplete implementation.
# Project Structure

Two valid layouts. Choose based on project size.

---

## Small / Medium Projects — Layer-First

```
{APP_ROOT}/app/
├── Domain/
│   └── {Ctx}/                                 # bounded context (Order, Customer, ...)
│       ├── Entity/                            # Aggregate roots, entities
│       ├── ValueObject/                       # Immutable readonly classes
│       ├── Repository/                        # Interfaces only
│       ├── Event/                             # Past-tense domain events
│       └── Exception/                         # Named after violated invariant
│
├── Application/
│   └── {Ctx}/
│       ├── UseCase/{Verb}{Noun}/              # one folder per use case
│       │   ├── {Verb}{Noun}Action.php         # readonly class, handle()
│       │   └── {Verb}{Noun}Data.php           # readonly DTO, co-located
│       ├── Command/                           # optional, when CQRS bus is used
│       └── Query/                             # optional, read-side
│
├── Infrastructure/
│   └── {Ctx}/
│       ├── Persistence/Eloquent/
│       │   ├── Models/                        # {Entity}Model.php
│       │   ├── Repositories/                  # Eloquent{Name}Repository.php
│       │   └── Mappers/                       # {Entity}Mapper.php
│       ├── Event/Listener/                    # SendXOnY listeners
│       ├── Job/                               # ProcessXJob.php
│       └── Provider/                          # {Ctx}ServiceProvider.php
│
└── Interface/
    └── Http/
        └── {Ctx}/
            ├── Controller/                    # Invokable, thin
            ├── Request/                       # FormRequest with authorize() + rules()
            ├── Resource/                      # JsonResource
            └── Policy/                        # registered via Gate::policy()

{APP_ROOT}/tests/
├── Unit/Domain/{Ctx}/                         # Pure PHP, no framework boot
├── Unit/Application/{Ctx}/                    # Action / UseCase tests
├── Feature/{Ctx}/                             # HTTP endpoints, full container
├── Integration/Infrastructure/{Ctx}/          # Real DB, repository round-trips
└── Architecture/                              # arch() layer boundary rules
```

---

## Large Projects — Domain-First (Modules)

Each module is a bounded context. Cross-module access goes through public Actions or events, not by reaching into another module's Domain.

```
{APP_ROOT}/app/
└── Modules/
    ├── Order/
    │   ├── Domain/
    │   ├── Application/
    │   ├── Infrastructure/
    │   └── UI/
    ├── Billing/
    │   ├── Domain/
    │   ├── Application/
    │   ├── Infrastructure/
    │   └── UI/
    └── Catalog/
        ├── Domain/
        ├── Application/
        ├── Infrastructure/
        └── UI/
```

Routing, service providers, migrations, and tests can live inside each module or at the app root depending on team preference; keep it consistent per project.

---

## Dependency Flow

```
HTTP Request
    ↓
Interface\Http\{Ctx}\Controller\*Controller
    ↓ (validated DTO)
Application\{Ctx}\*Action
    ↓ (domain calls)
Domain\{Ctx}\*Entity / *Repository
    ↑ (implementation)
Infrastructure\{Ctx}\Persistence\Eloquent\Repositories\Eloquent*Repository
    ↓
Database
```

---

## Naming Per Layer

| Layer | Namespace | Suffixes / forms |
|---|---|---|
| Domain | `App\Domain\{Ctx}\...` | `Order`, `OrderId`, `Money`, `OrderRepository` (interface) |
| Application | `App\Application\{Ctx}\UseCase\{Verb}{Noun}\...` | `CreateOrderAction`, `CreateOrderData`, `GetOrderQuery` |
| Infrastructure | `App\Infrastructure\{Ctx}\...` | `OrderModel`, `EloquentOrderRepository`, `StripePaymentGateway` |
| Interface/Http | `App\Interface\Http\{Ctx}\...` | `CreateOrderController`, `CreateOrderRequest`, `OrderResource` |
| Module variant | `App\Modules\{Ctx}\{Layer}\...` | same forms, single bounded-context tree |

Do not name anything `OrderManager`, `OrderHelper`, `OrderUtil`, or `OrderService` unless the project's legacy code already uses the suffix and renaming is out of scope.

---

## DTO Placement

DTOs belong in **Application** or **UI**. Never in Domain. A Domain VO is not a DTO; it expresses a concept (`Money`, `Email`), not a transport shape.

---

## Test Layout

Mirror the production layout under `{APP_ROOT}/tests/`. Bounded context is preserved on the test side too.

```
{APP_ROOT}/tests/
├── Unit/Domain/{Ctx}/OrderTest.php
├── Unit/Application/{Ctx}/CreateOrderActionTest.php
├── Feature/{Ctx}/CreateOrderTest.php
├── Integration/Infrastructure/{Ctx}/EloquentOrderRepositoryTest.php
└── Architecture/{Ctx}ArchTest.php
```

Module-first layouts mirror under `{APP_ROOT}/tests/Unit/Modules/{Ctx}/...`, `{APP_ROOT}/tests/Feature/Modules/{Ctx}/...`, etc.
# Module Generation

When creating a new feature at **Medium** or **Complex** complexity, generate the full set of files below. Do not collapse everything into one class.

---

## Example: `Order` module at Complex tier

### Domain (`{APP_ROOT}/app/Domain/Order/` or `{APP_ROOT}/app/Modules/Order/Domain/`)

```
Entity/
  Order.php                // Aggregate root
ValueObject/
  OrderId.php
  OrderStatus.php
  Money.php                // shared VO
Repository/
  OrderRepository.php      // Interface
Event/
  OrderCreated.php
  OrderPaid.php
Exception/
  OrderNotFoundException.php
  InvalidOrderStatusException.php
```

### Application (`{APP_ROOT}/app/Application/Order/` or `{APP_ROOT}/app/Modules/Order/Application/`)

```
UseCase/
  CreateOrder/
    CreateOrderAction.php
    CreateOrderData.php    // DTO co-located with the action
  PayOrder/
    PayOrderAction.php
    PayOrderData.php
  CancelOrder/
    CancelOrderAction.php
    CancelOrderData.php
Query/
  GetOrderDashboard/
    GetOrderDashboardQuery.php
    GetOrderDashboardHandler.php
    DashboardView.php      // Read-side DTO
```

### Infrastructure (`{APP_ROOT}/app/Infrastructure/Order/` or `{APP_ROOT}/app/Modules/Order/Infrastructure/`)

```
Persistence/Eloquent/
  Models/
    OrderModel.php
    OrderItemModel.php
  Repositories/
    EloquentOrderRepository.php
  Mappers/
    OrderMapper.php        // Eloquent ↔ Domain
Event/Listener/
  SendReceiptOnOrderPaid.php
Job/
  ProcessOrderPaymentJob.php
Provider/
  OrderServiceProvider.php
```

### UI (`{APP_ROOT}/app/Interface/Http/Order/` or `{APP_ROOT}/app/Modules/Order/UI/Http/`)

```
Controller/
  CreateOrderController.php    // invokable
  PayOrderController.php
  GetOrderController.php
Request/
  CreateOrderRequest.php       // extends FormRequest, has authorize() + rules()
  PayOrderRequest.php
Resource/
  OrderResource.php
Policy/
  OrderPolicy.php              // registered via Gate::policy()
```

### Tests

```
{APP_ROOT}/tests/Unit/Domain/Order/
  OrderTest.php
  OrderIdTest.php
  MoneyTest.php
{APP_ROOT}/tests/Unit/Application/Order/
  CreateOrderActionTest.php
{APP_ROOT}/tests/Feature/Order/
  CreateOrderTest.php
  PayOrderTest.php
{APP_ROOT}/tests/Integration/Infrastructure/Order/
  EloquentOrderRepositoryTest.php
{APP_ROOT}/tests/Architecture/
  OrderArchTest.php
```

---

## Example: `Invoice` module at Medium tier

Skip Domain entities and the repository interface. Use Eloquent directly.

```
{APP_ROOT}/app/Application/Invoice/UseCase/CreateInvoice/
  CreateInvoiceAction.php
  CreateInvoiceData.php
{APP_ROOT}/app/Infrastructure/Invoice/Persistence/Eloquent/Models/InvoiceModel.php
{APP_ROOT}/app/Interface/Http/Invoice/
  Controller/CreateInvoiceController.php
  Request/CreateInvoiceRequest.php
  Resource/InvoiceResource.php
{APP_ROOT}/tests/Feature/Invoice/CreateInvoiceTest.php
```

---

## Rules

- A feature is never a single class. Even a simple CRUD needs Model + Request + Controller + Resource + test.
- Create the directories even if a file is empty. Placeholders make intent clear.
- Wire service providers and route declarations in the same change, not later.
- If the module introduces a queue handler or event listener, register it in the same change.
# Templates

Pick the template set matching the complexity tier (see the Architecture Decision section above).

---

## Simple — CRUD

Eloquent + Form Request + Controller + API Resource.
Every file starts with `<?php\ndeclare(strict_types=1);`.

```php
// {APP_ROOT}/app/Models/Customer.php
final class Customer extends Model
{
    protected $fillable = ['name', 'email'];
}

// {APP_ROOT}/app/Interface/Http/Customer/Request/CreateCustomerRequest.php
final class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:customers,email'],
        ];
    }
}

// {APP_ROOT}/app/Interface/Http/Customer/Controller/CustomerController.php
final class CustomerController
{
    public function store(CreateCustomerRequest $request): CustomerResource
    {
        return CustomerResource::make(
            Customer::create($request->validated()),
        );
    }
}

// {APP_ROOT}/app/Interface/Http/Customer/Resource/CustomerResource.php
final class CustomerResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email];
    }
}
```

---

## Medium — Action + DTO

Action encapsulates the use case. DTO at the Application boundary. No repository interface yet.

```php
// {APP_ROOT}/app/Application/Customer/UseCase/CreateCustomer/CreateCustomerData.php
final readonly class CreateCustomerData
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}

    public static function fromRequest(CreateCustomerRequest $request): self
    {
        return new self(
            name:  $request->validated('name'),
            email: $request->validated('email'),
        );
    }
}

// {APP_ROOT}/app/Application/Customer/UseCase/CreateCustomer/CreateCustomerAction.php
final readonly class CreateCustomerAction
{
    public function __construct(
        private DatabaseManager $db,
        private Dispatcher $events,
    ) {}

    public function handle(CreateCustomerData $data): Customer
    {
        return $this->db->transaction(function () use ($data) {
            $customer = Customer::create([
                'name'  => $data->name,
                'email' => $data->email,
            ]);

            $this->events->dispatch(new CustomerRegistered($customer->id));

            return $customer;
        });
    }
}

// {APP_ROOT}/app/Interface/Http/Customer/Controller/CreateCustomerController.php
final class CreateCustomerController
{
    public function __invoke(
        CreateCustomerRequest $request,
        CreateCustomerAction $action,
    ): CustomerResource {
        return CustomerResource::make(
            $action->handle(CreateCustomerData::fromRequest($request)),
        );
    }
}
```

---

## Complex — DDD (Aggregate + Repository + Action)

```php
// {APP_ROOT}/app/Domain/Order/ValueObject/OrderId.php
namespace App\Domain\Order\ValueObject;

final readonly class OrderId
{
    public function __construct(public string $value)
    {
        if (!Str::isUuid($value)) {
            throw new InvalidArgumentException('OrderId must be a UUID');
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::uuid());
    }
}

// {APP_ROOT}/app/Domain/Order/Entity/Order.php
namespace App\Domain\Order\Entity;

final class Order
{
    /** @var list<OrderItem> */
    private array $items = [];

    private function __construct(
        public readonly OrderId $id,
        public readonly CustomerId $customerId,
        private OrderStatus $status,
    ) {}

    public static function create(CustomerId $customerId): self
    {
        return new self(OrderId::generate(), $customerId, OrderStatus::Draft);
    }

    public function addItem(Sku $sku, int $quantity, Money $price): void
    {
        if ($this->status !== OrderStatus::Draft) {
            throw new InvalidOrderStatusException('Cannot add items to a non-draft order');
        }
        $this->items[] = new OrderItem($sku, $quantity, $price);
    }

    public function markAsPaid(): void
    {
        if ($this->status !== OrderStatus::Confirmed) {
            throw new InvalidOrderStatusException('Only confirmed orders can be paid');
        }
        $this->status = OrderStatus::Paid;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }
}

// {APP_ROOT}/app/Domain/Order/Repository/OrderRepository.php
namespace App\Domain\Order\Repository;

interface OrderRepository
{
    public function save(Order $order): void;
    public function findById(OrderId $id): ?Order;
}

// {APP_ROOT}/app/Application/Order/UseCase/CreateOrder/CreateOrderAction.php
namespace App\Application\Order\UseCase\CreateOrder;

final readonly class CreateOrderAction
{
    public function __construct(
        private OrderRepository $orders,
        private Dispatcher $events,
    ) {}

    public function handle(CreateOrderData $data): OrderId
    {
        $order = Order::create(new CustomerId($data->customerId));

        foreach ($data->items as $item) {
            $order->addItem(new Sku($item->sku), $item->quantity, new Money($item->priceCents));
        }

        $this->orders->save($order);
        $this->events->dispatch(new OrderCreated($order->id));

        return $order->id;
    }
}

// {APP_ROOT}/app/Infrastructure/Order/Persistence/Eloquent/Repositories/EloquentOrderRepository.php
namespace App\Infrastructure\Order\Persistence\Eloquent\Repositories;

final class EloquentOrderRepository implements OrderRepository
{
    public function __construct(private OrderMapper $mapper) {}

    public function save(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $model = OrderModel::query()->updateOrCreate(
                ['id' => $order->id->value],
                $this->mapper->toRow($order),
            );
            $this->mapper->syncItems($model, $order);
        });
    }

    public function findById(OrderId $id): ?Order
    {
        $model = OrderModel::with('items')->find($id->value);
        return $model ? $this->mapper->toDomain($model) : null;
    }
}

// {APP_ROOT}/app/Interface/Http/Order/Controller/CreateOrderController.php
namespace App\Interface\Http\Order\Controller;

final class CreateOrderController
{
    public function __invoke(
        CreateOrderRequest $request,
        CreateOrderAction $action,
    ): JsonResponse {
        $orderId = $action->handle(CreateOrderData::fromRequest($request));

        return new JsonResponse(
            ['data' => ['id' => $orderId->value]],
            Response::HTTP_CREATED,
        );
    }
}
```

---

## Placeholders

Use these when generating new code. Replace `{Noun}`, `{Verb}`, `{params}` etc. before returning.

- Action: `{Verb}{Noun}Action::handle({Verb}{Noun}Data $data)`
- DTO: `readonly class {Verb}{Noun}Data`
- Repository interface: `interface {Noun}Repository { save(); findById(); }`
- Eloquent repo: `Eloquent{Noun}Repository implements {Noun}Repository`
- Controller: `{Verb}{Noun}Controller::__invoke({Verb}{Noun}Request, {Verb}{Noun}Action)`
# Architecture Decision

Match the approach to the actual complexity. Over-architecting a two-field form wastes time and blurs the codebase.

---

## Signals

Count the signals. Use the highest tier that matches.

**Simple (CRUD):**
- 0–2 business rules
- No state machine
- Entity is effectively a database record with validation
- Feature fits in one controller + one model + one Form Request

**Medium (Actions + DTO):**
- 2–3 business rules that require orchestration
- Some side effects (email, event, external call)
- Transactional write across 1–2 tables
- Would benefit from being unit-testable without HTTP

**Complex (DDD):**
- More than 3 interacting business rules
- State transitions with invariants (order lifecycle, subscription, billing)
- Multiple aggregates coordinated through events or a saga
- A bounded context with its own vocabulary
- High consistency or concurrency requirements

---

## Default to Simpler

When signals straddle two tiers, choose the simpler one. Promotion later is cheap. Demolition of premature DDD is expensive.

Call out in the response header *why* you chose a given tier so the reviewer can challenge it.

Example: `[FEATURE] [MEDIUM] [Action+DTO] // one write, one event, no state machine → Action`

---

## Anti-Signals for DDD

Do not pick DDD when:

- The team has no existing DDD code in the module.
- The feature is a one-off admin screen or report.
- The bounded context is unclear or still emerging.
- The only "rule" is field validation.

---

## When in Doubt

Ask the user: *"This looks like CRUD on the surface but there are N rules (list them). Do you want me to go with Actions + DTO, or is there a state machine here I am missing?"*

Proceed with the simpler tier if no answer arrives.
