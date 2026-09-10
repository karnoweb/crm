# CRM implementation status

Source of truth: host `CRM_PACKAGE.md` (version 5).

| Phase | Requirement | Status | Tests |
|---|---|---|---|
| 0 | composer.json, config, provider, key types, base model, enums, exceptions, after-commit events, architecture tests | done | bootstrap, key type, events, architecture, classifier |
| 1 | Lead, Interaction, Interest, scoring, recorder concurrency | done | recorder, schema, scoring, metrics |
| 2 | LeadService, conversion, archive, topInterests | done | lead service, conversion |
| 3 | Pipeline, Deal lifecycle, terminal won/lost | done | pipeline/deal |
| 4 | Activity, Note, Followup, morph map | done | morph + followup due |
| 5 | Segment allowlist evaluation | done | field/operator + opaque rejection |
| 6 | Campaign rank-based dispatch | done | enqueue/dispatch/ranks |
| 7 | Read-only reporting from Interaction | done | projection rebuild |
| 8 | Commands, docs polish, translations | done | command signatures/idempotency |

## Clarifications applied

- `Lead.attributes` JSON column is accessed via `hostAttributes()` because Eloquent reserves `$model->attributes`.
- `rfm_monetary_currency` stores the last audit currency; CRM never converts FX.
- `rfm_monetary_at` stores the host-supplied `occurredAt` from `recordMonetaryValue()` for audit only.
- `crm_segments.last_match_ids` is a refresh-command snapshot used only to publish `LeadSegmentChanged` diffs. `evaluate()` does not write it.
- SQLite `:memory:` cannot share a schema across OS processes; recorder/firstOrCreate concurrency is covered by the real unique-violation catch path plus composite unique inserts.
- `Illuminate\Contracts` is unused in `src/`. `Illuminate\Console` is used by package commands.
- `spatie/laravel-schemaless-attributes` is a direct dependency because the contract names it.

## Known issues

None remaining for the v1 contract.
