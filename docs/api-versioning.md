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

Fouten gebruiken een uniforme error-structuur met code en status:

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

## Migration Notes

- v1 endpoints blijven beschikbaar tijdens de introductie van v2.
- Nieuwe clients moeten zoveel mogelijk de responsewrapper gebruiken.
