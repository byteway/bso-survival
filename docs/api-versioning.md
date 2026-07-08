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
