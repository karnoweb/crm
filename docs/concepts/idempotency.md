# Idempotency

The host generates `idempotency_key`. The package only enforces `unique(lead_id, idempotency_key)`.

The key must be stable for one logical event (`commerce:order:152:purchased`). A random UUID on every retry defeats idempotency.

CRM validates string length only. It does not interpret key segments.
