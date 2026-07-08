# Fase 6 Backlog (Jira-style)

Deze backlog is direct bruikbaar als bron voor Jira-issues.
Structuur: eerst een tickettemplate, daarna 5 concrete tickets in prioritaire volgorde.

## Gebruik

1. Kopieer het template per nieuw issue.
2. Vul alle verplichte velden in.
3. Houd acceptatiecriteria testbaar en eenduidig.
4. Koppel elk ticket aan een testplan en reviewmoment.

---

## Ticket Template (Jira-achtig)

### Issue Header
- Issue Key: F6-XX
- Summary: 
- Epic: Fase 6 REST API Versioning Strategie
- Type: Story | Task | Spike | Bug
- Priority: P0 | P1 | P2 | P3
- Status: Todo
- Labels: fase-6, api, backlog
- Components: API, Service, Repository, Admin, Docs
- Estimate: XS | S | M | L | XL
- Story Points: 1 | 2 | 3 | 5 | 8 | 13
- Owner: 
- Reviewer: 

### Beschrijving
- Context:
- Doel:
- Scope in:
- Scope out:

### Technische aanpak
- 

### Acceptatiecriteria
- [ ] 
- [ ] 
- [ ] 

### Testcriteria
- Unit:
- Integratie:
- API:
- E2E:

### Afhankelijkheden
- 

### Risico's
- 

### Subtasks
- [ ] Analyse
- [ ] Implementatie
- [ ] Tests
- [ ] Documentatie
- [ ] Review

### Definition of Done
- [ ] Code gemerged
- [ ] Tests groen in CI
- [ ] API-contract geverifieerd
- [ ] Documentatie bijgewerkt
- [ ] Changelog/roadmap bijgewerkt

---

## Geprioriteerde tickets Fase 6

## F6-01 Datamodelbeslissing messages (architectuur lock)

### Issue Header
- Issue Key: F6-01
- Summary: Finaliseer message datamodel als single source of truth
- Epic: Fase 6 REST API Versioning Strategie
- Type: Story
- Priority: P0
- Status: Done
- Labels: fase-6, messages, datamodel, architecture
- Components: Repository, Schema, Docs
- Estimate: M
- Story Points: 5
- Owner: Unassigned
- Reviewer: Unassigned

### Beschrijving
- Context: Message-ontwerp in documentatie en runtime moet volledig gelijk lopen.
- Doel: Eenduidige datamodelkeuze en migratiepad vastleggen.
- Verificatie log: docs/F6-01_DEV_Verification.md
- Scope in:
- Beslisdocument voor message-opslagmodel
- Schema en docs op 1 lijn
- Migratiepad inclusief bestaande data
- Scope out:
- Nieuwe functionele features op message CRUD

### Technische aanpak
- Leg keuze vast: bestaande bso_survival_messages uitbreiden of alternatief model.
- Synchroniseer Schema, roadmap en API-documentatie.
- Definieer migratie/checks voor backwards compatibility.

### Acceptatiecriteria
- [x] Er is een expliciete architectuurbeslissing met rationale.
- [x] Runtime schema en documentatie tonen hetzelfde model.
- [x] Migratiepad voor bestaande data is beschreven en uitgevoerd op dev.

### Testcriteria
- Unit: schema-validatie test aanwezig.
- Integratie: migratie draait zonder dataverlies.
- API: n.v.t.
- E2E: n.v.t.

### Afhankelijkheden
- Geen

### Risico's
- Documentatie en code lopen opnieuw uiteen.

### Subtasks
- [x] Analyse huidige modelvarianten
- [x] Beslissing vastleggen
- [x] Schema/documentatie synchroniseren
- [x] Migratiecheck uitvoeren
- [x] Review

---

## F6-02 Dashboard messages volledige CRUD (incl DELETE)

### Issue Header
- Issue Key: F6-02
- Summary: Voltooi CRUD voor dashboard messages met audit logging
- Epic: Fase 6 REST API Versioning Strategie
- Type: Story
- Priority: P1
- Status: Done
- Labels: fase-6, messages, crud, rest
- Components: API, Service, Repository, Admin
- Estimate: L
- Story Points: 8
- Owner: Unassigned
- Reviewer: Unassigned

### Beschrijving
- Context: Bestaande flow ondersteunt vooral create/status toggles.
- Doel: Volledige mutatiecyclus beschikbaar maken voor beheer.
- Scope in:
- PATCH voor inhoudelijke update
- DELETE endpoint
- Admin UI-acties voor edit/delete
- Audit logging op alle mutaties
- Scope out:
- Time-window logica (visible_from/until)

### Technische aanpak
- Breid DashboardMessage REST controller uit met update/delete routes.
- Voeg servicevalidatie toe voor update/delete.
- Breid repository uit met update/delete querymethoden.
- Update admin page voor edit/delete workflow.

### Acceptatiecriteria
- [x] Admin kan message inhoudelijk wijzigen.
- [x] Admin kan message verwijderen met duidelijke feedback.
- [x] Widget/list endpoints tonen verwijderde messages niet meer.
- [x] Auditlog bevat create/update/delete/status mutaties.

### Testcriteria
- Unit: servicevalidatie update/delete.
- Integratie: repository update/delete querygedrag.
- API: PATCH/DELETE happy en foutpaden (400/404/409).
- E2E: admin edit/delete flow.

### Afhankelijkheden
- F6-01

### Risico's
- Onbedoeld verwijderen zonder herstelpad.

### Subtasks
- [x] API routes toevoegen
- [x] Service/repository uitbreiden
- [x] Admin UI bijwerken
- [x] Tests toevoegen
- [x] Documentatie bijwerken

---

## F6-03 Geldigheidsvenster messages (visible_from / visible_until)

### Issue Header
- Issue Key: F6-03
- Summary: Implementeer tijdgestuurde zichtbaarheid voor dashboard messages
- Epic: Fase 6 REST API Versioning Strategie
- Type: Story
- Priority: P1
- Status: Done
- Labels: fase-6, messages, scheduling, validation
- Components: Schema, API, Service, Repository, Widget
- Estimate: L
- Story Points: 8
- Owner: Unassigned
- Reviewer: Unassigned

### Beschrijving
- Context: Tijdvensters zijn functioneel ontworpen maar nog niet operationeel in volledige keten.
- Doel: Alleen actieve en tijd-geldige messages tonen.
- Scope in:
- Velden visible_from en visible_until in model/API/UI
- Validatie visible_until > visible_from
- Filterlogica in widget/list endpoints
- Scope out:
- Recurring schedules

### Technische aanpak
- Voeg datetime-velden toe aan schema en repository.
- Verwerk velden in create/update endpoints.
- Pas widget/list filtering toe op huidige tijd.
- Voeg heldere foutcodes toe voor tijdvalidatie.

### Acceptatiecriteria
- [x] Message met toekomstige visible_from is nog niet zichtbaar.
- [x] Message met verlopen visible_until is niet zichtbaar.
- [x] Ongeldige tijdcombinatie wordt geweigerd met duidelijke fout.
- [x] Sortering blijft correct na tijdfilters.

### Testcriteria
- Unit: validatie op datetime-combinaties. (DashboardMessageServiceTest)
- Integratie: repository filtering op tijdvenster. (RepositoryTest)
- API: create/update met geldige/ongeldige windows. (DashboardMessageRestControllerTest)
- E2E: widget zichtbaarheid rond randmomenten. (handmatige smoke test: `tests/manual/f6-03-smoke.sh`)

### Afhankelijkheden
- F6-01

### Risico's
- Tijdzonefouten bij vergelijking datetimes.

### Subtasks
- [x] Schema-update
- [x] Servicevalidatie
- [x] Repositoryfiltering
- [x] API/UI verwerking
- [x] Tests en docs

---

## F6-04 Fijnmazige rechten voor scorebeheer

### Issue Header
- Issue Key: F6-04
- Summary: Introduceer capability-based autorisatie voor scorebeheer
- Epic: Fase 6 REST API Versioning Strategie
- Type: Story
- Priority: P2
- Status: Done
- Labels: fase-6, security, permissions
- Components: API, Admin, Core
- Estimate: M
- Story Points: 5
- Owner: Unassigned
- Reviewer: Unassigned

### Beschrijving
- Context: Toegang leunt primair op manage_options.
- Doel: Domeinspecifieke rechten voor score- en messagebeheer.
- Scope in:
- Nieuwe capabilities (bijv. manage_survival_scores, manage_survival_messages)
- Permission callbacks en admin menu capabilities aanpassen
- Rolmapping documenteren
- Scope out:
- Externe IAM-koppelingen

### Technische aanpak
- Definieer capabilities in plugin bootstrap/activatie.
- Vervang of combineer manage_options checks in controllers/admin pages.
- Voeg centrale capability helper toe voor consistentie.

### Acceptatiecriteria
- [x] Gebruiker zonder capability krijgt geen mutatietoegang.
- [x] Gebruiker met capability kan toegestane acties uitvoeren.
- [x] Administrator-flow blijft backwards compatible.
- [x] Nonce- en permission checks blijven beide actief.

### Testcriteria
- Unit: capability helper en guard-logica. (CapabilitiesTest)
- Integratie: toegang per rol/capability matrix. (AdminScoreRestControllerTest, DashboardMessageRestControllerTest)
- API: 403/401 voor onvoldoende rechten. (permission callback tests)
- E2E: admin schermtoegang per rol. (handmatige validatie aanbevolen)

### Afhankelijkheden
- Geen (kan parallel na F6-01)

### Risico's
- Rechten regressie op bestaande beheerflows.

### Subtasks
- [x] Capability model definiëren
- [x] Guards aanpassen
- [x] Rolmapping testen
- [x] Docs bijwerken
- [x] Security review

---

## F6-05 V2-thema's uitwerken naar uitvoerbare stories

### Issue Header
- Issue Key: F6-05
- Summary: Concretiseer v2 API-thema's naar sprintklare backlog
- Epic: Fase 6 REST API Versioning Strategie
- Type: Task
- Priority: P3
- Status: In Progress
- Labels: fase-6, planning, v2
- Components: Docs, API
- Estimate: M
- Story Points: 5
- Owner: Unassigned
- Reviewer: Unassigned

### Beschrijving
- Context: v2-thema's zijn nu strategisch geformuleerd.
- Doel: Van thema naar concrete stories met contract en testplan.
- Scope in:
- Advanced filtering stories
- Bulk-operatie stories
- Metadata-blok stories
- Per story: endpoint, payload, foutcodes, testaanpak, afhankelijkheden
- Scope out:
- Directe implementatie van alle v2 stories

### Technische aanpak
- Splits elk v2-thema in kleine, leverbare stories.
- Voeg per story acceptatiecriteria en testcases toe.
- Definieer prioriteit, estimate en afhankelijkheden.
- Werkdocument met sprintklare stories: `docs/F6-05_V2_Story_Backlog.md`.

### Acceptatiecriteria
- [x] Geprioriteerde v2 backlog is opgesteld.
- [x] Elke story heeft API-contract en acceptatiecriteria.
- [x] Elke story heeft testaanpak en afhankelijkheden.
- [x] Backlog is klaar voor sprintplanning zonder extra analyse.

### Testcriteria
- Unit: n.v.t.
- Integratie: n.v.t.
- API: contract review checklist.
- E2E: n.v.t.

### Afhankelijkheden
- Input uit F6-01 t/m F6-04

### Risico's
- Te grote stories die niet sprintbaar zijn.

### Subtasks
- [x] Thema's opsplitsen
- [x] Contracten uitwerken
- [x] AC en testplan toevoegen
- [x] Schatting en prioriteit bepalen
- [ ] Review met team

---

## Aanbevolen uitvoerorde

1. F6-01
2. F6-02
3. F6-03
4. F6-04
5. F6-05

## Sprintvoorstel

- Sprint A: F6-01 + start F6-02
- Sprint B: afronding F6-02 + F6-03
- Sprint C: F6-04 + F6-05
