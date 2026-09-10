# CRM package architecture

The package is a **CRM domain**, not a host application. It stores data, computes projections, and publishes events. It never sends SMS, never sends email, never creates orders, and never calls host-domain services.

## Lead identity

**`Lead` هویت دائمی طرف/مخاطب CRM (CRM party/contact identity) است. کلمه‌ی «Lead» فقط نام مرحله‌ی acquisition آن را توصیف می‌کند، نه هویت معنایی همیشگی‌اش.** یعنی وقتی `status` به `customer` می‌رسد، رکورد همچنان همان `Lead` است — نه این‌که مدل عوض شود یا رکورد دیگری ساخته شود.

The model and table are not renamed when status becomes `customer`. If a future release splits Contact / Customer / Prospect, this sentence is the migration boundary.

## Layers

| Layer | Path | Role |
|---|---|---|
| Service | `src/Services/` | Use-case: transactions, locks, lifecycle |
| Support | `src/Support/` | Key types, decay, validators, after-commit dispatch |
| Event | `src/Events/` | Output API — identifiers and primitives only, after commit |
| Exception | `src/Exceptions/` | Catchable domain failures |
| Model | `src/Models/` | Persistence and relationships |
| Enum | `src/Enums/` | Closed vocabularies |

## Boundaries

1. **Input API** is the injected services. The `Crm` facade is convenience only.
2. **Output API** is events. Every Output API event is published with `DB::afterCommit()`.
3. **No real foreign keys** to host models. `user_id`, `branch_id`, and `assigned_to` are soft configurable keys.
4. **Tenant/branch is host-owned.** The package never resolves `Tenant::current()`.
5. **Interaction is the source of truth.** Interest and aggregated Lead fields are rebuildable projections.
6. **`metadata` and `Lead.attributes` are opaque.** They are never used for scoring or segmentation.

## Out of package scope

HTTP, UI, authorization, SMS/email delivery, Commerce, Accounting, tickets, and host User/role management.
