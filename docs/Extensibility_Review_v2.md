# Extensibility Review – BSO Survival v2

## Samenvatting

BSO Survival v2 is goed voorbereidt voor toekomstige uitbreidingen op de volgende punten:
- ✅ Modulaire architectuur met duidelijke lagen
- ✅ Configureerbare operationele parameters
- ✅ Datamodel ondersteunt multi-event en seizoensdata
- ⚠️ Scoremethode-plugin patroon moet expliciet worden gedefinieerd
- ⚠️ Dashboard widget-architectuur moet worden vastgesteld
- ⚠️ REST API endpoints moeten meer generiek worden gestructureerd
- ⚠️ Custom field/meta-data patroon ontbreekt

---

## 1. Architecturale Flexibiliteit

### Huidige Toestand ✅

De 4-lagen architectuur (Presentation, Application, Domain, Infrastructure) biedt goede scheiding van verantwoordelijkheden:

```
Presentation Layer
├── Admin UI (Settings, Planning, Managment)
├── Mobile Frontend (Dashboard, Score entry)
└── REST API

Application Layer
├── Planning service
├── Scoring service
├── Joker service
├── Messaging service
└── Certificate service

Domain Layer
├── Rooster optimization
├── Score calculation
├── Ranking logic
└── Status transitions

Infrastructure Layer
├── Custom WordPress tables
├── Hooks & filters
├── Audit logging
└── REST endpoints
```

Voordeel: Aanpassing in één laag breekt andere lagen niet.

### Aanbevelingen ✨

1. **Expliciete service interfaces documenteren:**
   - Definieer interfaces voor Planning, Scoring, Ranking, Dashboard services
   - Dit faciliteert mock/stub implementaties en swappable services

2. **Hook strategy uitwerken:**
   - Definieer kritische WordPress hooks waar plugins kunnen inhaken:
     - `bso_survival_score_calculated` – na scoreberekening
     - `bso_survival_ranking_updated` – na rangschikking update
     - `bso_survival_event_status_changed` – bij statusovergang
     - `bso_survival_certificate_generated` – na certificaat generatie
     - `bso_survival_message_created` – bij meldingen

3. **Action/Filter registry:**
   - Centraal overzicht van alle beschikbare hooks (soort custom post types)
   - Documentatie per hook: context, expected parameters, return value

---

## 2. Datamodel Uitbreidbaarheidheid

### Huidige Toestand ✅

Het ERD is goed genormaliseerd en voorziet in de volgende sterke punten:

| Entiteit | Voordeel |
|---|---|
| `Event` | Volledig gescheiden per survivaldag; multi-event ready |
| `Part/PartRule` | Scoremethode is los attribuut; makkelijk uit te breiden |
| `Assignment` | Flexibele team-onderdeel-tijdslot koppeling |
| `ScoreEntry` | Ruwe + genormaliseerde + positie + rankpunten; breed toepasselijk |
| `JokerUsage` | Eenmalig per event per team per onderdeel geborgd |
| `Message` | Meldingstype is string; makkelijk uit te breiden |
| `Certificate` | Gegenereerd artefact; herbruikbaar voor andere documenten |

### Beperkingen ⚠️

1. **Geen custom fields / meta-data:**
   - Vraag: "Kunnen we toekomstig extra velden op Team, Part of Event opslaan?"
   - Antwoord: Niet zonder migration.
   - **Oplossing:** Voeg `meta_data` JSON-veld toe aan Team, Part, Event:
     ```sql
     ALTER TABLE bso_survival_events ADD COLUMN meta_data JSON DEFAULT '{}'
     ALTER TABLE bso_survival_teams ADD COLUMN meta_data JSON DEFAULT '{}'
     ALTER TABLE bso_survival_parts ADD COLUMN meta_data JSON DEFAULT '{}'
     ```

2. **Geen versioning / audit trail per entiteit:**
   - Vraag: "Kunnen we de evolutie van team-samenstelling of onderdeel-regels bijhouden?"
   - Antwoord: Centraal auditlog bestaat, maar niet per entiteit.
   - **Oplossing:** Voeg centraal `bso_survival_audit_log` tabel toe met timestamp, entity_type, entity_id, old_value, new_value, changed_by.

3. **Geen tagging/categorie voor Part:**
   - Vraag: "Kunnen we onderdelen groeperen (bijv. 'water', 'land', 'techniek')?"
   - Antwoord: Alleen via Part.name, geen taxonomie.
   - **Oplossing:** Voeg `category_id` FK toe naar `bso_survival_categories` tabel; of gebruik WordPress custom taxonomies.

4. **Geen scheduling constraints per Part:**
   - Vraag: "Kunnen we onderdelen met bepaalde hardware eisen tegen elkaar uitplannen?"
   - Antwoord: Planningslogica ziet dit niet.
   - **Oplossing:** Voeg `scheduling_constraints` JSON-veld toe aan Part (bijv. `{"not_concurrent": [3, 7, 12]}` — zeg: onderdeel 1 mag niet gelijktijdig met 3, 7, 12 worden gespeeld).

### Aanbevelingen ✨

1. **Migratiestrategie vastleggen:**
   - FO moet aangeven welke datamodel-wijzigingen minimaal nodig zijn vóór v2.1
   - Alle migrations moeten reversible en tested zijn
   - Backup-strategie moet expliciet zijn

2. **JSON-velden gebruiken voor flexibiliteit:**
   ```sql
   -- Voeg toe aan relevante tabellen:
   -- event: meta_data (voor seizoen, sponsor, custom settings)
   -- team: meta_data (voor team-specifieke data)
   -- part: meta_data (voor toekomstige attributes)
   -- assignment: meta_data (voor specifieke timeslot-koppeling opmerkingen)
   ```

3. **Relations voor toekomstige scenarios:**
   - Voeg `part_family` tabel in voor deel-groepen (water onderdelen, etc.)
   - Voeg `team_division` tabel in voor divisies of kategorieën teams
   - Voeg `scoring_profile` tabel in voor variabele scoring rules

---

## 3. Scoremethode Plugin-Patroon

### Huidige Toestand ⚠️

Scoremethode is hard-coded als `scoring_mode` enum in `PartRule`:
- Ondersteunde waarden: `time`, `points`, `distance` (impliciet in FO)
- Berekening gebeurt in domain service (scoreberekening logica)
- Geen callback-patroon voor custom scoremethodes

### Probleem

Vraag: "Wat als we toekomstig een 'techniek score' methode willen toevoegen (bijv. aantal correcte herstels)?"
Antwoord: Code-wijziging nodig in Scoring service + enum-uitbreiding.

### Aanbevelingen ✨

**Plugin-patroon invoeren:**

1. **Registratie-systeem voor scoremethodes:**
   ```php
   // In plugin bootstrap
   add_action('bso_survival_register_scoring_methods', function() {
       register_scoring_method('time', TimeScoringMethod::class);
       register_scoring_method('points', PointsScoringMethod::class);
       register_scoring_method('distance', DistanceScoringMethod::class);
       // Toekomstig custom:
       // register_scoring_method('technique', TechniqueScoringMethod::class);
   });
   ```

2. **Interface definiëren:**
   ```php
   interface ScoringMethodInterface {
       public function score_raw_value($raw_value, $config);
       public function normalize_to_points($scored, $max_expected);
       public function generate_position_proposal($scores_all_teams);
       public function get_description(): string;
   }
   ```

3. **Service instantiatie via registry:**
   ```php
   $method = ScoringMethodRegistry::get('time');
   $normalized = $method->normalize_to_points($raw_value, $config);
   ```

4. **Datamodel-update:**
   - `PartRule.scoring_mode` blijft, maar wordt gekoppeld aan registry
   - Voeg `scoring_config` JSON-veld toe voor methode-specifieke parameters

---

## 4. Workflow Extensibility

### Huidige Toestand ✅

Workflows zijn goed gemodelleerd als stap-voor-stap processen:
- Workflow 1: Teaminschrijving
- Workflow 2: Score invoer
- Workflow 3b: Score + joker + tussenstand
- Workflow 4: Fallback
- Workflow 4b: Leiding controle
- Workflow 5: Dag sluiten
- Workflow 5b: Eindstand
- Workflow 6: Plaatsing onderdeel → eindstand

Voordeel: Stappen zijn discreet en kunnen gefilterd/uitgebreid worden.

### Beperkingen ⚠️

1. **Geen workflow state machine:**
   - Vraag: "Kunnen we custom validatiestappen invoegen tussen score-invoer en opslag?"
   - Antwoord: Mogelijk via filters, maar niet expliciet gedefinieerd.

2. **Geen workflow event-publishing:**
   - Vraag: "Kunnen we een extern systeem notificeren wanneer een score wordt opgeslagen?"
   - Antwoord: Niet built-in; manual hook nodig.

### Aanbevelingen ✨

1. **Workflow engine basis:**
   ```php
   // Abstracte workflow class
   abstract class Workflow {
       protected $steps = [];
       
       public function register_step($name, StepInterface $step, $priority = 10) {
           $this->steps[$name] = [$step, $priority];
       }
       
       public function execute($context) {
           // Execute steps in order
           foreach ($this->steps as [$step, $priority]) {
               $context = $step->execute($context);
               do_action('bso_survival_workflow_step_completed', $step, $context);
           }
       }
   }
   ```

2. **State transitions als hooks:**
   ```php
   // Per entity type (Event, Team, ScoreEntry):
   do_action('bso_survival_event_transitioning_from_concept_to_planned');
   do_action('bso_survival_event_transitioned_to_active');
   ```

3. **Pre/post hooks op kritieke momenten:**
   - `bso_survival_before_score_validation`
   - `bso_survival_after_score_validation`
   - `bso_survival_before_ranking_recalc`
   - `bso_survival_after_ranking_recalc`

---

## 5. Dashboard Extensibility

### Huidige Toestand ⚠️

Dashboard wordt beschreven als centraal geheel maar niet als modulaire widget-architectuur.

Secties:
- Tijdslotverloopdiagram
- Onderdeel-rapportagestatus
- Teampositieoverzicht
- Contactzoeker
- Meldingenbeheer
- Fallback-scoreinvoer
- Scheidsrechter-navraag

Probleem: Geen expliciete widget-registratie of custom-widget-patroon.

### Aanbevelingen ✨

1. **Widget-registratie systeem:**
   ```php
   // Dashboard class
   class Dashboard {
       protected $widgets = [];
       
       public function register_widget($id, WidgetInterface $widget, $position = 'main') {
           $this->widgets[$id] = [$widget, $position];
       }
   }
   
   // Usage in extension/custom code:
   add_action('bso_survival_dashboard_init', function() {
       $dashboard->register_widget('custom_analytics', new CustomAnalyticsWidget());
   });
   ```

2. **Widget-interface:**
   ```php
   interface WidgetInterface {
       public function render($context): string;
       public function get_data($filters);
       public function get_permissions(): array;
   }
   ```

3. **Dashboard sectie-typen:**
   - Diagrammen (Mermaid, Chart.js)
   - Lijsten (Teams, Messages, Assignments)
   - Vormen (Score-invoer, Fallback)
   - Navigatie (Contactzoeker, Meldingen)

---

## 6. REST API Extensibility

### Huidige Toestand ⚠️

FO vermeldt REST API als transport voor mobiel dashboard en score-invoer, maar endpoints zijn niet expliciet gedefinieerd.

Impliciet verwacht:
- `GET /wp-json/bso-survival/v1/events/{id}/assignments` – planning
- `POST /wp-json/bso-survival/v1/scores` – score-invoer
- `GET /wp-json/bso-survival/v1/events/{id}/standings` – tussenstand
- `GET /wp-json/bso-survival/v1/messages` – meldingen

### Beperkingen ⚠️

1. **Geen versioning strategie:**
   - Vraag: "Kunnen we API wijzigen zonder bestaande clients te breken?"
   - Antwoord: `/v1/` prefix aanwezig, maar geen rollout-strategie.

2. **Geen filterings/sorting standaard:**
   - Waarschijnlijk nodig: `/v1/scores?team_id=5&part_id=3&timeslot_id=2`
   - Geen mentioning van query-parameters.

3. **Geen pagination voor grote datasets:**
   - Vraag: "Wat als een event 1000 messages heeft?"
   - Antwoord: Niet gespecificeerd.

### Aanbevelingen ✨

1. **API versioning roadmap:**
   ```
   v1 – MVP (GET/POST basics)
   v2 – Batch operations, custom filters
   v3 – GraphQL-optie voor complexe queries
   ```

2. **Standaard REST-strukuur per resource:**
   ```
   GET /wp-json/bso-survival/v1/events
   GET /wp-json/bso-survival/v1/events/{id}
   POST /wp-json/bso-survival/v1/events
   PUT /wp-json/bso-survival/v1/events/{id}
   DELETE /wp-json/bso-survival/v1/events/{id}
   
   Query-parameters:
   - ?filter[status]=active
   - ?sort=created_at&direction=desc
   - ?page=1&per_page=20
   - ?include=teams,assignments (related resources)
   ```

3. **Webhooks voor externe integraties:**
   ```
   Webhooks die kunnen worden geregistreerd:
   - event.created
   - event.activated
   - event.closed
   - score.recorded
   - ranking.updated
   - certificate.generated
   - message.created
   ```

---

## 7. Integratie-punten

### Huidige Toestand ⚠️

FO vermeldt communicatie (email, certificaten) maar niet integratie-patroon.

### Beperkingen ⚠️

1. **Mail-service niet abstrakt:**
   - Vraag: "Kunnen we toekomstig SMS of push notifications gebruiken?"
   - Antwoord: Waarschijnlijk WordPress mail(), maar niet gefacadeerd.

2. **Certificate generatie hard-gecodeerd:**
   - Vraag: "Kunnen we toekomstig PDF of ander format gebruiken?"
   - Antwoord: Format waarschijnlijk niet uitwisselbaar.

3. **Geen third-party API integraties:**
   - Vraag: "Kunnen we integreren met Google Forms, Eventbrite, of live-scoring tools?"
   - Antwoord: Niet voorzien in huiding design.

### Aanbedelingen ✨

1. **Mail-service abstracter:**
   ```php
   interface NotificationChannelInterface {
       public function send($recipient, $subject, $body, $context = []);
   }
   
   // Implementations:
   class EmailChannel implements NotificationChannelInterface { ... }
   class SMSChannel implements NotificationChannelInterface { ... }
   class PushChannel implements NotificationChannelInterface { ... }
   
   // Registry:
   NotificationManager::send('email', $recipient, $subject, $body);
   ```

2. **Document generatie abstracter:**
   ```php
   interface DocumentGeneratorInterface {
       public function generate($template, $data): string; // blob or path
       public function get_format(): string; // 'pdf', 'html', 'png'
   }
   
   // Usage:
   $generator = DocumentGeneratorRegistry::get('pdf');
   $pdf = $generator->generate('certificate', $context);
   ```

3. **Webhook registry voor externe webhooks:**
   ```php
   add_action('bso_survival_event_activated', function($event) {
       WebhookDispatcher::dispatch('event.activated', $event);
   });
   ```

---

## 8. Configuratie Extensibility

### Huidige Toestand ✅

Operationele parameters zijn nu configureerbaar:
- Max teams
- Max onderdelen
- Actieve duur
- Uitleg/instructie duur
- Wisseltijd
- Rankpunten-formule

### Aanbevelingen ✨

1. **Settings framework:**
   ```php
   // Admin settings form builder pattern
   class SettingsPanel {
       public function register_setting($key, $type, $default, $label, $help) { ... }
       public function get_setting($key) { ... }
       public function update_setting($key, $value) { ... }
   }
   
   // Usage:
   $panel->register_setting('max_teams', 'number', 22, 'Max teams', 'Maximaal aantal teams');
   $panel->register_setting('ranking_formula', 'formula', '(max + 1 - pos) * 10', 'Ranking formule', '');
   ```

2. **Settings validatie:**
   - Validators per setting-type (number, string, formula, enum)
   - Custom validators voor complexe checks (bijv. formule syntax)

3. **Feature flags:**
   ```php
   // Future-proofing voor A/B testing van features
   if (FeatureFlag::enabled('advanced_scheduling')) {
       // Include geavanceerde planning UI
   }
   ```

---

## 9. Performance & Schaling

### Huidge Toestand ⚠️

FO specificeert geen performance targets of schaling strategie.

### Kritieke Vragen

1. **Team count:** 22 teams is standaard. Wat als er 100 teams willen deelnemen?
   - Dashboard: realtime ranking van 100 teams kan trager zijn
   - Planning: algoritmische complexiteit kan toenemen
   - Database queries: index-strategieën nodig

2. **Event volume:** 1 event per jaar. Wat als we 10 events tegelijk beheren?
   - Database growth: exponentieel naar tabel-grootte
   - Queries: multi-tenant filtering nodig (event_id predicate op elke tabel)

3. **Audit trail:** Eén log entry per score-wijziging. Bij 22 teams × 20 onderdelen × 12 rondes = 5,280 entries.
   - Storage: per jaar toeneemt, archivering nodig
   - Query time: historisch filtering kan trager worden

### Aanbevelingen ✨

1. **Indexeerstraategie:**
   ```sql
   -- Kritische indexes
   CREATE INDEX idx_event_id ON bso_survival_scores(event_id);
   CREATE INDEX idx_team_event ON bso_survival_scores(team_id, event_id);
   CREATE INDEX idx_assignment_timeslot ON bso_survival_assignments(timeslot_id);
   CREATE INDEX idx_timeslot_event ON bso_survival_timeslots(event_id, status);
   CREATE INDEX idx_audit_entity ON bso_survival_audit_log(entity_type, entity_id);
   ```

2. **Caching strategie:**
   - Tussenstand: cachen tot volgende score-invoer
   - Planning: cachen per event
   - Dashboard diagrammen: cache per 5 seconden
   - Invalidatie op score-invoer, ranking-recalc, dag-sluiting

3. **Query optimalisatie:**
   - Batch-loads van related data (teams, assignments, scores)
   - Vermijd N+1 queries op mobile API
   - Lazy-load optionele relaties

4. **Data archivering:**
   - Historische events archiveren na 1 jaar
   - Audit log comprimeren/aggregeren
   - Separate archief-tabellen

---

## 10. Toekomstige Uitbreidingen: Go/No-Go Analyse

### Uitbreidingsscenario's

| Scenario | Feasibility | Werk nodig |
|---|---|---|
| **Multi-event seizoensoverzicht** | ✅ Go | Datamodel: OK. API: filters op event_id. Frontend: dashboard aanpassing. |
| **Geavanceerde planningsoptimalisatie** | ✅ Go | Domain service: constraint solver. Geen datamodel-breaking change. |
| **Live push updates (WebSocket)** | ⚠️ Conditie | Infrastructuur: WebSocket server. API: event streaming. Vorig design niet vereist. |
| **Uitgebreide rapportage/export** | ✅ Go | REST API: export endpoints. Document generator: PDF/Excel. |
| **Integratie met Eventbrite/Google Forms** | ⚠️ Conditie | Externe API's: Webhook dispatchers nodig. Teaminschrijving: import flow. |
| **Techniek/custom scoremethodes** | ⚠️ Conditie | Scoring plugin-patroon: EERST implementeren. |
| **Mobiel app (native iOS/Android)** | ✅ Go | REST API: volledig genoeg. Offline mode: sync nodig. |
| **Real-time samenwerking (multi-leiding)** | ⚠️ Conditie | Optimistic locking of versioning: nodig. Conflict resolution. |
| **Vergevorderde analytics (ML/AI)** | ✅ Go | Data export: OK. Externe tools kunnen analyseren. |

### Implementatie-volgorde Aanbeveling

**Fase 1 (v2.1 – 2-3 maanden):**
1. Scoring plugin-patroon (nodig voor toekomstige methodes)
2. Dashboard widget-architectuur (nodig voor custom dashboards)
3. Datamodel uitbreiding (meta_data, audit log)

**Fase 2 (v2.2 – 3-4 maanden):**
1. REST API generalisatie (versioning, filtering, pagination)
2. Webhook dispatcher
3. Notification channel abstraction

**Fase 3 (v2.3+ – Later):**
1. Geavanceerde planning
2. Multi-event/seizoensvergelijking
3. Custom rapportage

---

## 11. Samenvatting & Actionable To-Do's

### Sterken 💪

1. **Modulaire 4-lagen architectuur** – goede basis voor uitbreidingen
2. **Configureerbare operationele parameters** – geen hard-coding van kernwaarden
3. **Datamodel normalisatie** – goed voorbereid op multi-event
4. **REST API presence** – baseline voor mobiel en externe integratie

### Zwakken 🚫

1. **Geen plugin-patroon voor scoremethodes** – nieuwe methodes vereisen code-wijziging
2. **Dashboard niet als widget-architectuur** – moeilijk custom widgets toe te voegen
3. **Geen tagging/categorie voor onderdelen** – groepering lastig
4. **Geen custom fields/meta-data** – toekomstige data vastgesteld in datamodel

### Actionable Verbeteringen (Prioriteit)

**Kritiek (blokkeer v2.1 roadmap):**
- [ ] Implement scoring method plugin-registry (vereist voor v2.1)
- [ ] Expand datamodel: add meta_data JSON fields
- [ ] Design and document WordPress action/filter hook strategy

**Hoog (aanbevolen vóór productie):**
- [ ] Dashboard widget-registratie systeem
- [ ] Notification channel abstraction (mail, SMS, push)
- [ ] REST API versioning roadmap en filtering standaard

**Gemiddeld (roadmap):**
- [ ] Webhook dispatcher voor externe integratie
- [ ] Document generator abstraction (PDF, HTML, etc.)
- [ ] Caching en indexeerstraategie

**Laag (future nice-to-have):**
- [ ] Feature flag system
- [ ] Advanced scheduling optimizer
- [ ] Data archiving policy

---

*Opgesteld op 7 juli 2026 · Review van BSO Survival v2 FO extensibility*
