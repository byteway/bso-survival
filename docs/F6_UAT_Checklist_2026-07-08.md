# Fase 6 UAT Checklist

Datum: 8 juli 2026
Versie: 1.0
Doel: Functionele acceptatie van F6-01 t/m F6-05 in runtime-omgeving.

## UAT Scope

- F6-01 Datamodelbeslissing messages
- F6-02 Dashboard messages CRUD
- F6-03 Geldigheidsvenster messages
- F6-04 Fijnmazige rechten voor scorebeheer
- F6-05 v2 stories S1 t/m S5

## Benodigde voorbereiding

- WordPress runtime is bereikbaar en plugin is actief.
- Een test-event_id bestaat met voldoende testdata.
- Er is minimaal 1 admin-account en 1 account zonder mutatierechten.
- Lokale runtime secrets zijn aanwezig in .dev/wp-dev.env.

## Testuitvoering volgorde

### Stap 1. Baseline smoke: shortcode runtime

Actie:
- Draai scripts/wp-runtime-shortcode-smoke.sh

Verwacht resultaat:
- Script eindigt succesvol.
- Geen fatale fouten.
- Kern shortcodes renderen zonder regressie.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 2. F6-03 visibility smoke

Actie:
- Draai scripts/wp-runtime-f6-03-smoke.sh

Verwacht resultaat:
- Eindregel toont PASS.
- Geen zichtbaarheid voor future of verlopen meldingen.
- Ongeldig venster wordt afgewezen.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 3. F6-02 CRUD messages via admin flow

Actie:
- Maak een nieuwe dashboard message aan.
- Bewerk tekst of type van dezelfde message.
- Deactiveer en activeer de message.
- Verwijder de message.

Verwacht resultaat:
- Elke mutatie geeft duidelijke feedback in beheer.
- Verwijderde message is niet meer zichtbaar in widget of lijst.
- Geen onverwachte validatiefouten bij geldige invoer.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 4. F6-04 rechtenmatrix controle

Actie:
- Test score of message mutatie met admin-account.
- Test dezelfde mutatie met account zonder benodigde capability.

Verwacht resultaat:
- Geautoriseerde gebruiker mag muteren.
- Ongeautoriseerde gebruiker wordt correct geblokkeerd.
- Nonce en permissiechecks blijven actief.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 5. F6-05-S1 v2 advanced filtering

Actie:
- Roep v2 message list op met combinaties van scope, status, type, visible_at, search, page, per_page.

Verwacht resultaat:
- Deterministische filtering.
- Correcte paginering met total en total_pages.
- Ongeldige filterwaarden geven duidelijke 400 fout.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 6. F6-05-S2 v2 bulk status

Actie:
- Voer bulk-status update uit op geldige message_ids binnen event.
- Voer bulk-status uit met minimaal 1 id buiten event.

Verwacht resultaat:
- Geldige set wordt volledig bijgewerkt.
- Gemengde set geeft conflictrespons.
- Geen update buiten event-scope.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 7. F6-05-S3 v2 bulk delete

Actie:
- Voer bulk-delete uit met confirm gelijk aan true.
- Herhaal zonder confirm true.

Verwacht resultaat:
- Met confirm true worden records binnen event verwijderd.
- Zonder confirm true wordt verzoek geweigerd.
- Geen delete buiten event-scope.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 8. F6-05-S4 v2 meta contract

Actie:
- Doe create of update op v2 messages en v2 scores met geldig meta object.
- Doe create of update met onbekende meta sleutel.

Verwacht resultaat:
- Geldige meta wordt geaccepteerd.
- Onbekende sleutel geeft invalid_meta_block.
- Backward compat met bestaande message meta_data blijft intact.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

### Stap 9. F6-05-S5 governance-check

Actie:
- Controleer dat contracten, fallbackpaden en release-gates up-to-date zijn in governance-document.

Verwacht resultaat:
- Compatibiliteitsmatrix aanwezig en volledig.
- Migratiechecklist en release-gates ingevuld voor deze release.

Aftekenen:
- [ ] PASS
- [ ] FAIL
- Bewijs notitie:

## Sign-off

- UAT uitgevoerd door:
- Datum:
- Besluit:
  - [ ] Geaccepteerd voor release
  - [ ] Niet geaccepteerd, herstel nodig
- Opmerkingen:

## Uitvoeringslog 8 juli 2026

- Stap 1 uitgevoerd: PASS
  - Resultaat: alle directe shortcode-smokes geslaagd.
  - Opmerking: dashboard, parts, teams, event_overview en event_summary renderen correct.
- Stap 2 uitgevoerd: PASS
  - Resultaat: F6-03 visibility smoke geslaagd.
  - Opmerking: future en verlopen meldingen onzichtbaar, geldig venster zichtbaar, ongeldige tijdcombinatie geweigerd.

## Referenties

- docs/Fase6_Formele_Afsluiting_2026-07-08.md
- docs/Fase6_Backlog_Jira_Template.md
- docs/F6-05_V2_Story_Backlog.md
- docs/api-versioning.md
- docs/V2_Contract_Governance_Compat_Matrix.md
- docs/F6-03_Handmatige_Smoke_Checks.md
