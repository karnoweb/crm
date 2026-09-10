# Karnoweb CRM documentation

- [Architecture](concepts/architecture.md) — Lead identity, layers, boundaries
- [Scoring](concepts/scoring.md) — locked formula and decay
- [Idempotency](concepts/idempotency.md) — host-generated keys
- [Implementation status](implementation-status.md)
- [QA report](qa-report.md)

Primary API: inject the services. The `Crm` facade is convenience only.

All Output API events are published after database commit.
