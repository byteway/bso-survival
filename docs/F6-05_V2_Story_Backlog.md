# F6-05 V2 Story Backlog

Laatste update: 8 juli 2026.
Status: In Progress.

## Doel

Van strategische v2-thema's naar sprintklare stories met API-contract, foutcodes, testaanpak en afhankelijkheden.

## Scope en uitgangspunten

- Geen breaking changes in v1.
- v2-functionaliteit onder `/wp-json/bso-survival/v2/...`.
- Response-standaard blijft `success/data` en gestandaardiseerde foutcodes.
- Bestaande F6-01 t/m F6-04 keuzes zijn randvoorwaardelijk.

## Storyoverzicht (geprioriteerd)

| Story Key | Titel | Prioriteit | Estimate | Afhankelijkheden |
|---|---|---|---|---|
| F6-05-S1 | v2 geavanceerde message filtering | P1 | M | F6-03 | 
| F6-05-S2 | v2 bulk status updates messages | P1 | M | F6-02, F6-04 |
| F6-05-S3 | v2 bulk delete messages met safeguards | P1 | M | F6-02, F6-04 |
| F6-05-S4 | v2 gestandaardiseerd metadata-contract | P2 | M | F6-01 |
| F6-05-S5 | v2 contract governance + compat matrix | P2 | S | F6-05-S1..S4 |

Statusupdate:

- [x] F6-05-S1 geïmplementeerd in runtime (`/wp-json/bso-survival/v2/dashboard/messages`)
- [x] F6-05-S2 geïmplementeerd in runtime (`/wp-json/bso-survival/v2/dashboard/messages/bulk-status`)
- [ ] F6-05-S3
- [ ] F6-05-S4
- [ ] F6-05-S5

---

## F6-05-S1 v2 geavanceerde message filtering

### Endpoint

- `GET /wp-json/bso-survival/v2/dashboard/messages`

### Query parameters

- `event_id` (int, verplicht)
- `scope` (`all|event|global`, optioneel)
- `status` (`actief|inactief`, optioneel)
- `type` (`info|warning|success|urgent`, optioneel)
- `visible_at` (ISO datetime, optioneel; default = now)
- `search` (string, optioneel; full-text like op text)
- `page` (int, default 1)
- `per_page` (int, default 20, max 100)

### Foutcodes

- `invalid_filter` (400)
- `invalid_pagination` (400)
- `message_list_failed` (500)

### Acceptatiecriteria

- Filtering op combinatie van `scope/status/type` werkt deterministisch.
- `visible_at` retourneert alleen tijd-geldige records.
- `search` filtert op `text` zonder lege resultaten bij exact match.
- Paginering (`page/per_page/total/total_pages`) blijft correct.

### Testaanpak

- Unit: filter normalisatie en validatie.
- Integratie: repository query-combinaties.
- API: happy path + ongeldige filterwaarden.

---

## F6-05-S2 v2 bulk status updates messages

### Endpoint

- `POST /wp-json/bso-survival/v2/dashboard/messages/bulk-status`

### Payload

```json
{
  "event_id": 12,
  "message_ids": [11, 12, 15],
  "status": "inactief",
  "changed_by": "planner"
}
```

### Foutcodes

- `invalid_bulk_payload` (400)
- `bulk_update_conflict` (409)
- `bulk_update_failed` (500)

### Acceptatiecriteria

- Alleen records binnen `event_id` worden aangepast.
- Gemengde set (deels ongeldig) geeft duidelijke conflict-response.
- Auditlog bevat batchcontext (`count`, `status`, `changed_by`).
- Permission + nonce checks blijven actief.

### Testaanpak

- Unit: payload-validatie en statusregels.
- Integratie: repository bulk-update transactioneel gedrag.
- API: success + partial conflict + unauthorized.

---

## F6-05-S3 v2 bulk delete messages met safeguards

### Endpoint

- `POST /wp-json/bso-survival/v2/dashboard/messages/bulk-delete`

### Payload

```json
{
  "event_id": 12,
  "message_ids": [19, 21],
  "confirm": true,
  "changed_by": "admin"
}
```

### Foutcodes

- `invalid_bulk_payload` (400)
- `bulk_delete_conflict` (409)
- `bulk_delete_failed` (500)

### Acceptatiecriteria

- Zonder `confirm=true` wordt delete altijd geweigerd.
- Alleen records van het meegegeven event worden verwijderd.
- Widget/list endpoints tonen verwijderde records niet meer.
- Auditlog bevat verwijderde ids en actor.

### Testaanpak

- Unit: confirm-guard en id-validatie.
- Integratie: repository delete met event-scope.
- API: happy path + confirm ontbreekt + unauthorized.

---

## F6-05-S4 v2 gestandaardiseerd metadata-contract

### Endpoint-impact

- `POST/PATCH /dashboard/messages`
- `POST/PATCH /scores/entries`
- Nieuwe v2 endpoints nemen `meta` object op met uniforme shape.

### Contract

```json
{
  "meta": {
    "source": "admin",
    "labels": ["operations"],
    "trace_id": "abc-123"
  }
}
```

### Foutcodes

- `invalid_meta_block` (400)

### Acceptatiecriteria

- `meta` shape is consistent over v2 endpoints.
- Onbekende top-level velden in `meta` worden geweigerd.
- Serialization/deserialization blijft backward compatible met v1 `meta_data`.

### Testaanpak

- Unit: meta-schema validatie.
- Integratie: opslag/lees roundtrip.
- API: invalid meta block en compat scenario.

---

## F6-05-S5 v2 contract governance + compat matrix

### Deliverables

- Compatibiliteitsmatrix v1/v2 per endpoint.
- Deprecation-notes + migratiechecklist.
- Release-gate checklist voor v2 contract-review.

### Acceptatiecriteria

- Elke v2 story heeft een expliciet contractblok.
- v1 fallbackpad is per endpoint gedocumenteerd.
- Team kan sprintplanning doen zonder aanvullende analyse.

### Testaanpak

- API review checklist (design-time).
- Contract snapshots in docs.

---

## Sprintvoorstel

1. Sprint C.1: S1 + S2 afgerond
2. Sprint C.2: S3 + S4
3. Sprint C.3: S5 en release-gates

## Open punten

- Beslissen of bulk-operaties synchroon of queue-based moeten zijn.
- Bepalen of `search` SQL-like of dedicated index nodig heeft.
- Definiëren van maximale batchgrootte (advies: 100 ids).
