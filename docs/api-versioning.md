# BSO Survival REST API Versioning

## Current Version
v1 - MVP functionality

## Versioning Strategy

Versioning is onderdeel van de URL:
- /wp-json/bso-survival/v1/...
- /wp-json/bso-survival/v2/... (future)

## Compatibility Rules

- v1 blijft backwards-compatible binnen dezelfde major.
- Breaking changes gaan uitsluitend naar een nieuwe major (v2, v3, ...).
- Deprecated gedrag krijgt een expliciete changelog-notitie en migratiepad.

## Response Standard

Nieuwe endpoints gebruiken een standaardwrapper:

```json
{
  "success": true,
  "data": {
    "...": "..."
  }
}
```

Fouten gebruiken een uniforme code/message/status semantiek.
In WordPress-runtime worden fouten als `WP_Error` teruggegeven met deze JSON-vorm:

```json
{
  "code": "invalid_score_input",
  "message": "raw_value must be numeric.",
  "data": {
    "status": 400,
    "details": {}
  }
}
```

In non-WP testcontext (fallback) blijft de interne wrappervorm beschikbaar:

```json
{
  "success": false,
  "error": {
    "code": "invalid_score_input",
    "message": "raw_value must be numeric.",
    "status": 400,
    "details": {}
  }
}
```

## Planned v2 Themes

- uitgebreide filtering
- paginering op lijst-endpoints
- bulk-operaties voor beheeracties
- gestandaardiseerde metadata-blokken

Concretisering naar stories staat in `docs/F6-05_V2_Story_Backlog.md`:
- F6-05-S1: geavanceerde message filtering
- F6-05-S2: bulk status updates
- F6-05-S3: bulk delete met safeguards
- F6-05-S4: metadata-contract standaardisatie
- F6-05-S5: contract governance en compat matrix

## v2 Implemented (S1)

Endpoint:

```http
GET /wp-json/bso-survival/v2/dashboard/messages
```

Ondersteunde query-parameters:
- `event_id` (verplicht, int)
- `scope` (`all`, `event`, `global`)
- `status` (`actief`, `inactief`)
- `type` (`info`, `warning`, `success`, `urgent`)
- `visible_at` (datetime, default `now`)
- `search` (max 120 chars)
- `page` (default `1`)
- `per_page` (default `20`, max `100`)

Foutcodes:
- `invalid_filter` (400)
- `invalid_pagination` (400)
- `message_list_failed` (500)

## v2 Implemented (S2)

Endpoint:

```http
POST /wp-json/bso-survival/v2/dashboard/messages/bulk-status
```

Payload:

```json
{
  "event_id": 12,
  "message_ids": [11, 12, 13],
  "status": "inactief",
  "changed_by": "planner"
}
```

Foutcodes:
- `invalid_bulk_payload` (400)
- `bulk_update_conflict` (409)
- `bulk_update_failed` (500)

## v2 Implemented (S3)

Endpoint:

```http
POST /wp-json/bso-survival/v2/dashboard/messages/bulk-delete
```

Payload:

```json
{
  "event_id": 12,
  "message_ids": [19, 21],
  "confirm": true,
  "changed_by": "admin"
}
```

Safeguard:
- `confirm` moet expliciet `true` zijn, anders geen delete.

Foutcodes:
- `invalid_bulk_payload` (400)
- `bulk_delete_conflict` (409)
- `bulk_delete_failed` (500)

## v2 Implemented (S4)

Gestandaardiseerd `meta` contract is toegevoegd op v2 write-endpoints:

```http
POST  /wp-json/bso-survival/v2/dashboard/messages
PATCH /wp-json/bso-survival/v2/dashboard/messages/{message_id}
POST  /wp-json/bso-survival/v2/scores/entries
PATCH /wp-json/bso-survival/v2/scores/entries/{score_entry_id}
```

Contractvorm:

```json
{
  "meta": {
    "source": "admin",
    "labels": ["operations"],
    "trace_id": "abc-123"
  }
}
```

Validatieregels:
- Alleen `source`, `labels`, `trace_id` zijn toegestaan als top-level keys.
- Onbekende keys geven `invalid_meta_block` (400).
- `labels` moet een array van niet-lege strings zijn.

Backwards compatibility:
- Voor dashboard messages blijft v1/v2 `meta_data` ondersteund.
- Wanneer `meta` aanwezig is op v2, wordt dit genormaliseerd naar de bestaande opslag in `meta_data`.

Foutcodes:
- `invalid_meta_block` (400)

## v2 Implemented (S5)

Governance deliverables zijn formeel vastgelegd in:

- `docs/V2_Contract_Governance_Compat_Matrix.md`

Inhoud:
- v1/v2 compatibiliteitsmatrix per endpoint.
- Deprecation-notes en migratiechecklist.
- Release-gate checklist voor contract-review.

Resultaat:
- Team kan v2 wijzigingen plannen en releasen zonder aanvullende analyse.

## Filtering Examples (v1)

Voorbeeld: dashboardmeldingen ophalen voor een event met scope-filter en paginering.

```http
GET /wp-json/bso-survival/v1/dashboard/messages?event_id=12&scope=all&page=1&per_page=20
```

Ondersteunde query-parameters op dit endpoint:
- `event_id` (verplicht, int)
- `scope` (`all`, `event`, `global`)
- `page` (optioneel, default `1`)
- `per_page` (optioneel, default `20`, max `100`)
- `limit` (legacy fallback wanneer `per_page` ontbreekt)

Voorbeeldresponse:

```json
{
  "success": true,
  "data": {
    "event_id": 12,
    "scope": "all",
    "items": [
      {
        "id": 9,
        "event_id": 12,
        "type": "warning",
        "text": "Teambriefing over 10 minuten",
        "visibility": "intern",
        "status": "actief"
      }
    ],
    "pagination": {
      "page": 1,
      "per_page": 20,
      "total": 37,
      "total_pages": 2
    }
  }
}
```

## Recalculate Endpoint (v1)

Voor operationeel herstel na bulk-import of handmatige correcties is een herbereken-endpoint beschikbaar:

```http
POST /wp-json/bso-survival/v1/scores/recalculate
```

Payload:

```json
{
  "event_id": 12,
  "part_id": 31,
  "changed_by": "planner"
}
```

Response:

```json
{
  "success": true,
  "data": {
    "result": {
      "event_id": 12,
      "part_id": 31,
      "team_count": 8,
      "positions": {
        "101": 1,
        "102": 2
      }
    }
  }
}
```

## Messages Datamodel Upgrade (v1)

De tabel `bso_survival_messages` bevat nu ook een `meta_data` kolom (JSON als string) voor uitbreidbare metadata, bijvoorbeeld kanaal-tags, herkomst of UI-hints.

## Migration Notes

- v1 endpoints blijven beschikbaar tijdens de introductie van v2.
- Nieuwe clients moeten zoveel mogelijk de responsewrapper gebruiken.
