# CRM package QA report

## 1. Executive summary

`karnoweb/crm` implements the v5 contract as an isolated Laravel 13 domain package. Input is services; output is identifier-only events published with `DB::afterCommit()`. Interaction is the source of truth. Commerce, Accounting, SMS, and Ticket Chat are not dependencies.

**Readiness:** v1 contract is implemented and the package suite is green (`117` tests / `3186` assertions). True OS-process concurrency is limited on Windows + SQLite `:memory:`; uniqueness is still enforced at the database and exercised through the real unique-violation catch path.

## 2. Implementation status by phase

| Phase | Status |
|---|---|
| 0 Foundation | complete |
| 1 Lead / Interaction / Interest | complete |
| 2 Lead services / conversion | complete |
| 3 Pipeline / Deals | complete |
| 4 Activity / Notes / Followups | complete |
| 5 Segments | complete |
| 6 Campaigns | complete |
| 7 Reporting | complete |
| 8 Commands / docs | complete |

## 3. Test statistics

- Runner: PHPUnit 13.3.1 + Orchestra Testbench 11, SQLite `:memory:`
- Result: **117 passed, 0 failed**
- Assertions: **3186**
- Suites: Architecture, Unit, Feature (including migration, event/rollback, rank, scoring, commands)

## 4. Architecture audit

- `src/` is scanned with token-aware use/FQCN parsing (case-insensitive).
- Forbidden namespaces: `Karnoweb\Accounting`, `Karnoweb\Hr`, `Karnoweb\SmsSender`, `Karnoweb\LaravelTicketChat`, `Illuminate\Mail`, `Illuminate\Notifications`, `App\Models\`.
- `composer.json` `require` + `require-dev` are scanned. No Karnoweb sibling packages. Runtime deps are illuminate/* plus `spatie/laravel-schemaless-attributes` (named by the contract).
- No `DealObserver`. Morph map uses `Relation::morphMap()` merge, not `enforceMorphMap()`.

## 5. Concurrency audit

| Operation | Coverage | Limitation |
|---|---|---|
| `firstOrCreateForUser()` | insert-first + unique(`user_id`) catch path + repeated calls | No `pcntl_fork` on this Windows runner |
| `InteractionRecorderService::record()` | insert-first + unique(`lead_id`,`idempotency_key`) catch path + peer pre-insert race simulation | Same SQLite `:memory:` process limit |
| `dispatchPending()` | lockForUpdate + second call is a no-op | Same |

The catch path is the production race handler. Sequential replay plus a committed peer insert is the strongest deterministic simulation available here.

## 6. Transaction / rollback audit

Covered: Lead create, Interaction record, Deal win, Pipeline create. Rollback leaves no rows and does not publish Output events.

`enqueueRecipientsFromSegment()` writes inside one transaction. `createWithDefaultStages()` is atomic (pipeline + open stages + one won + one lost).

## 7. Event audit

| Event | After commit | Idempotent / once |
|---|---|---|
| InteractionRecorded | yes | not on replay |
| AffinityThresholdReached | yes | only on threshold crossing |
| LeadCreated | yes | not on firstOrCreate replay |
| LeadConverted | yes | not on repeat convert |
| DealStageChanged | yes | not on same-stage move |
| DealWon / DealLost | yes | not on repeat win/lose |
| FollowupDue | yes | `notified_at` prevents duplicates |
| CampaignDispatchRequested | yes | only `dispatchPending()`, pending → dispatch_requested |
| LeadSegmentChanged | yes | refresh command membership diff only |

Payloads are identifiers/primitives. `CampaignDispatchRequested` rejects provider/contact fields.

## 8. Security / boundary audit

- No host model imports in `src/`.
- No SMS/email/mail/notification sends.
- No real FKs to host users/branches.
- Polymorphic targets: `crm_lead`, `crm_deal` only.
- Segment rules are a closed enum; `metadata.*` / `attributes.*` cannot be selected.
- `Lead.attributes` and `Interaction.metadata` are opaque store/return.

## 9. Projection / source-of-truth audit

- Interaction is immutable (update/delete throw).
- Interest and Lead score/totals are rebuilt from Interaction.
- `crm:recalculate-affinity` rebuilds projections.
- Reports' top-interest metric reads Interaction, not cached Interest. Deleting Interest rows does not change that report.

## 10. Reporting audit

- `conversionFunnel` uses Lead capture/conversion plus Interaction counts (date-filterable).
- `winLossSummary` reads Deal rows (optional pipeline).
- `topInterests` applies the locked scoring formula to Interaction.

## 11. Defects found and fixed during QA

1. **UUID/ULID column type assertion** — SQLite reports `varchar`. Test now accepts driver-native types. Implementation already used `uuid()`/`ulid()`.
2. **Same-day decay float noise** — `age_in_days` is whole days (`floor(seconds/86400)`), matching `decay(age_in_days)`.
3. **`convert()` same `user_id` but non-customer** — idempotent early-return now requires status `customer` as well, so conversion still occurs.
4. **`CampaignDispatchRequested` producer gap for membership events** — `LeadSegmentChanged` had no producer. Refresh command now diffs `last_match_ids` and publishes after commit. `evaluate()` remains side-effect free.
5. **Partial campaign enqueue** — recipient inserts are now one transaction.
6. **won ↔ lost cross transitions** — regression tests added; services already rejected them.
7. **Forbidden campaign payload keys** — enforced on event construction.

## 12. Remaining limitations

- True multi-process races are not executed on this host (Windows, no `pcntl`, SQLite `:memory:`). Database uniqueness + insert-first + lockForUpdate remain the production guarantees.
- `last_match_ids` on `crm_segments` is a command-owned snapshot so `LeadSegmentChanged` can be exact. It is not used by `evaluate()`.
- Eloquent's `$model->attributes` bag conflicts with a column named `attributes`. Public API is `Lead::hostAttributes()`.
- `rfm_monetary_currency` is an audit column implied by `recordMonetaryValue()`; the original table list omitted it.
- v1 does not implement `anonymizeLead()`, incremental segment evaluation, FX, Company/B2B, or Deal reopen.

## 13. Final readiness assessment

The package satisfies the architectural and behavioral contract of `CRM_PACKAGE.md` v5: isolation, Interaction-as-source-of-truth, terminal lifecycles, rank-based campaign status, closed segment allowlist, after-commit Output API, and insert-first idempotency.

It is ready to be required by a host as `karnoweb/crm` and consumed through DI services plus event listeners. Host work (HTTP, auth, SMS/email senders, Commerce) remains outside this package.
