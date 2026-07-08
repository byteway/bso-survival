# ADR-0001 Message Storage Model

- Status: Accepted
- Date: 2026-07-08
- Owners: BSO Survival team
- Scope: F6-01 (Fase 6 verfijning)

## Context

Binnen Fase 6 bestond documentatieverschil over het opslagmodel voor dashboardmeldingen:
- runtime gebruikt bestaande tabel `bso_survival_messages`
- een deel van het ontwerp verwees naar een nieuwe tabelvariant

Deze divergentie blokkeert verdere verfijning (F6-02 CRUD en F6-03 zichtbaarheidsvensters) omdat API, repository en migraties anders op verschillende modellen gaan ontwikkelen.

## Decision

We kiezen `bso_survival_messages` als single source of truth voor v1/MVP en vervolgstappen in Fase 6.

Geen nieuwe message-tabel introduceren in F6-01.

## Consequences

Positief:
- Geen datamigratie naar een tweede tabel nodig.
- Bestaande service/repository/controllerketen blijft geldig.
- F6-02 en F6-03 kunnen iteratief op hetzelfde model doorbouwen.

Trade-offs:
- Kolommen voor uitgebreide scheduling/workflow worden additief toegevoegd op bestaand model.
- Legacy velden (`type`, `text`, `visibility`, `status`) blijven leidend totdat een grote versie-upgrade dit verandert.

## Canonical Datamodel (current)

Tabel: `bso_survival_messages`

Kernkolommen:
- `id`
- `event_id`
- `type`
- `text`
- `visibility`
- `status`
- `meta_data`
- `created_at`
- `updated_at`

Toelichting:
- `meta_data` is JSON-string voor uitbreidbare metadata zonder breaking schemawijzigingen.

## Migration Strategy

F6-01 voert geen tabelrename of tabelsplitsing uit.

Migratiebeleid vanaf nu:
1. Alleen additieve schemawijzigingen op `bso_survival_messages`.
2. Geen destructieve wijziging op bestaande kolommen in v1.
3. Nieuwe velden (bijv. `visible_from`, `visible_until`) additief toevoegen met veilige defaults (`NULL` waar passend).
4. Query's en services backward-compatible houden voor bestaande records zonder nieuwe velden.

## Validation Checklist (F6-01)

- [x] Architectuurbeslissing expliciet vastgelegd.
- [x] Runtime schema en documentatie uitgelijnd op hetzelfde tabelmodel.
- [x] Migratiepad voor bestaande data beschreven zonder data-move.
- [ ] Migratiepad uitgevoerd op alle doelomgevingen (dev/stage/prod) en bevestigd.

## Follow-up

- F6-02: volledige message CRUD op dit model afronden.
- F6-03: visible window velden additief implementeren op dit model.
- F6-05: v2 stories expliciet laten verwijzen naar dit ADR als baseline.
