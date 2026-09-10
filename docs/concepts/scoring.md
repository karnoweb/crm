# Scoring

Locked formula:

```
effective_weight = interaction.weight × scoring.weights[type]
Interest.score   = Σ (effective_weight × decay(age_in_days))
```

`decay(age)` is exponential with `scoring.decay_half_life_days`: `0.5 ^ (age_in_days / half_life)`.

Unknown `interaction.type` values persist. Their policy weight is `scoring.default_weight` (default `0`).

`php artisan crm:recalculate-affinity` rebuilds every Interest and Lead projection from Interaction.
