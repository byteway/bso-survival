# V2 Contract Governance en Compatibiliteitsmatrix

Laatste update: 8 juli 2026.
Status: Actief beheerd.

## Doel

Dit document borgt contractconsistentie tussen v1 en v2 en geeft een operationeel release-gate kader.

## Scope

- Endpointcontracten voor messages en admin score invoer.
- v1 fallbackpaden per v2 endpoint.
- Migratiechecklist voor clients.
- Release-gate checklist voor contract-review.

## Compatibiliteitsmatrix v1-v2

| Domein | v2 Endpoint | v2 Contractkern | v1 Fallbackpad | Compatibiliteitstype | Opmerkingen |
|---|---|---|---|---|---|
| Messages list | `GET /wp-json/bso-survival/v2/dashboard/messages` | Advanced filters (`status`, `type`, `visible_at`, `search`) + paginering | `GET /wp-json/bso-survival/v1/dashboard/messages` | Partial fallback | v1 ondersteunt `event_id`, `scope`, `page`, `per_page`, `limit`; geavanceerde filters ontbreken. |
| Messages create | `POST /wp-json/bso-survival/v2/dashboard/messages` | Uniform `meta` block (`source`, `labels`, `trace_id`) | `POST /wp-json/bso-survival/v1/dashboard/messages` | Full fallback met mapping | v2 `meta` wordt genormaliseerd naar bestaande opslag in `meta_data`. |
| Messages update | `PATCH /wp-json/bso-survival/v2/dashboard/messages/{message_id}` | Uniform `meta` block + update velden | `PATCH /wp-json/bso-survival/v1/dashboard/messages/{message_id}` | Full fallback met mapping | v1 gebruikt `meta_data`; v2 weigert onbekende `meta` keys. |
| Messages bulk status | `POST /wp-json/bso-survival/v2/dashboard/messages/bulk-status` | Batchstatus + event-scope guard | `PATCH /wp-json/bso-survival/v1/dashboard/messages/{message_id}` of `POST .../{message_id}/activate|deactivate` per item | Operational fallback | Geen v1 batch-endpoint; fallback is iteratief per message. |
| Messages bulk delete | `POST /wp-json/bso-survival/v2/dashboard/messages/bulk-delete` | Batchdelete met `confirm=true` guard | `DELETE /wp-json/bso-survival/v1/dashboard/messages/{message_id}` per item | Operational fallback | Geen v1 batchdelete; fallback is iteratief per message. |
| Scores create | `POST /wp-json/bso-survival/v2/scores/entries` | Uniform `meta` block + score submit | `POST /wp-json/bso-survival/v1/scores/entries` | Full fallback | v1 endpoint blijft functioneel; v2 voegt contractvalidatie voor `meta` toe. |
| Scores update | `PATCH /wp-json/bso-survival/v2/scores/entries/{score_entry_id}` | Uniform `meta` block + score update | `PATCH /wp-json/bso-survival/v1/scores/entries/{score_entry_id}` | Full fallback | Zelfde businessregels; v2 voegt contractvalidatie voor `meta` toe. |

## Contract snapshots per story (S1-S4)

### S1 snapshot

- Endpoint: `GET /wp-json/bso-survival/v2/dashboard/messages`
- Kern: advanced filtering + paginering + standaard responsewrapper.
- Foutcodes: `invalid_filter`, `invalid_pagination`, `message_list_failed`.

### S2 snapshot

- Endpoint: `POST /wp-json/bso-survival/v2/dashboard/messages/bulk-status`
- Kern: event-scope validatie en batchstatus update.
- Foutcodes: `invalid_bulk_payload`, `bulk_update_conflict`, `bulk_update_failed`.

### S3 snapshot

- Endpoint: `POST /wp-json/bso-survival/v2/dashboard/messages/bulk-delete`
- Kern: harde safeguard `confirm=true`.
- Foutcodes: `invalid_bulk_payload`, `bulk_delete_conflict`, `bulk_delete_failed`.

### S4 snapshot

- Endpoints:
  - `POST/PATCH /wp-json/bso-survival/v2/dashboard/messages`
  - `POST/PATCH /wp-json/bso-survival/v2/scores/entries`
- Kern: uniform `meta` object met sleutelset `source`, `labels`, `trace_id`.
- Foutcode: `invalid_meta_block`.

## Deprecation-notes

- Er zijn momenteel geen hard-deprecated v1 endpoints.
- v1 blijft ondersteund voor MVP-compatibiliteit.
- Nieuwe clients moeten v2 gebruiken voor advanced filtering, bulk-operaties en uniforme `meta` validatie.
- Bij toekomstige deprecatie geldt minimaal 1 release-cyclus waarschuwing met migratie-instructie in changelog.

## Migratiechecklist (v1 client naar v2)

- [ ] Inventariseer welke clientcalls messages en scores muteren.
- [ ] Migreer message list calls naar v2 wanneer advanced filtering nodig is.
- [ ] Vervang iteratieve statuswijzigingen door `bulk-status` waar batchgedrag gewenst is.
- [ ] Vervang iteratieve deletes door `bulk-delete` met expliciete `confirm=true`.
- [ ] Introduceer gestandaardiseerd `meta` object op v2 write-calls.
- [ ] Verwijder onbekende top-level meta-keys; behoud alleen `source`, `labels`, `trace_id`.
- [ ] Verifieer capability + nonce gedrag ongewijzigd in beheerclients.
- [ ] Valideer foutafhandeling op nieuwe v2 foutcodes.

## Release-gate checklist (v2 contract review)

### Gate A: Contract

- [ ] Endpoint, methode en payload staan expliciet in docs.
- [ ] Alle foutcodes zijn gedocumenteerd met statuscode.
- [ ] Responsewrapper volgt `success/data` standaard.
- [ ] Compatibiliteit met v1 fallback is expliciet benoemd.

### Gate B: Security en toegang

- [ ] Capability checks zijn actief en getest.
- [ ] Nonce-validatie is actief op muterende endpoints.
- [ ] Event-scope validaties blokkeren cross-event mutaties.

### Gate C: Betrouwbaarheid

- [ ] Unit/integratie/API tests voor happy en foutpaden zijn groen.
- [ ] Regressiesuite is groen voor merge/release.
- [ ] Runtime smoke checks voor kritieke shortcode/API flows zijn uitgevoerd.

### Gate D: Governed release

- [ ] Compatibiliteitsmatrix is bijgewerkt.
- [ ] Deprecation-notes en migratiechecklist zijn bijgewerkt.
- [ ] Storystatus in backlogdocumentatie staat op Done.

## Ownership en werkafspraken

- Product owner: bepaalt deprecatieplanning.
- Tech lead: bewaakt contractfreeze en review gates.
- QA: valideert checklist Gates B/C per release.
