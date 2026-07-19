# Implementation Roadmap – BSO Survival v2 Extensibility Gaps

## Voortgangsupdate (7 juli 2026)

Afgerond in de huidige codebase:
- read-only shortcode `bso_survival_dashboard`
- read-only shortcode `bso_survival_parts`
- read-only shortcode `bso_survival_teams`
- gecombineerde shortcode `bso_survival_event_overview` met `compact=yes/no`
- compacte gecombineerde shortcode `bso_survival_event_summary`
- admin notice flow voor renderfouten op ongeldige `event_id`
- testsuite groen (104/104)
- Fase 2 stap 2.1-2.3 geïmplementeerd: ScoringMethodInterface, ScoringMethodRegistry en defaults (time/points/distance)
- Fase 2 stap 2.4 geïmplementeerd: bootstrap + action hook voor custom scoremethodes
- Fase 2 stap 2.5 geïmplementeerd: PartRule datamodel bevat scoring_mode, scoring_config, unit, tiebreaker_mode
- Fase 2 stap 2.6 geïmplementeerd: FO sectie berekeningen geactualiseerd naar registry-driven scoreflow
- Fase 2 open punten uitgevoerd: minimale admin-configuratie voor PartRules + scoring_config-opslag + e2e scoringsflow tests
- Productie-hardening doorgevoerd op admin-koppeling: nonce/CSRF-checks, strengere sanitization en mode-specifieke configuratievelden
- Fase 3 gestart: DashboardWidgetInterface + DashboardWidgetRegistry + hook `bso_survival_dashboard_widgets_init`
- Fase 3 basiswidgets toegevoegd (timeslot, ranking, rapportage, meldingen, contactzoeker, fallback-score)
- Dashboard-rendering aangesloten op registry-sectie `main`
- Fase 3.5 uitgevoerd: sectie-indeling `main` + `operations` met capability filtering en custom hook-registratie tests
- Fase 3.5 uitgevoerd: widget-specifieke assets/dependencies toegevoegd en per sectie ge-enqueued
- Fase 3.6 gestart: event-specifieke widget-layoutopslag (enable/order per sectie) met adminpagina
- Fase 3.6 gestart: dashboard renderflow gebruikt opgeslagen layout voor zichtbaarheid/volgorde
- Fase 3.6 afgerond: admin UX verfijnd met drag-and-drop volgorde, live preview en sectie-validatiefeedback
- Fase 3.6 afgerond: REST endpoint toegevoegd voor dashboard layout (GET/POST per event)
- Fase 3.6 verfijning: admin gebruikt realtime REST-save zonder reload met inline succes/foutfeedback
- Fase 4 afgerond: scoreflow gebruikt filterhooks voor normalized points en position proposal
- Fase 4 afgerond: score entry service voegt before_score_validation en score_recorded hooks toe
- Fase 4 afgerond: event status service voegt before_event_status_change en event_status_changed hooks toe
- Fase 4 afgerond: ranking refresh en certificate generation voegen ranking_updated en certificate_generated hooks toe
- Fase 4 afgerond: audit logging service voegt before_audit_log_write, audit_log_written en audit_log_failed hooks toe
- Volgende fase gestart: EventCloseoutService koppelt eventstatus, certificaatregistratie en audit logging in een eerste dagafsluitingsflow
- Volgende fase uitgebreid: REST-trigger voor closeout/publicatie en frontend read-only/publicatieflow zijn toegevoegd

Let op: deze roadmap blijft een gap-document. Niet alle voorgestelde fases zijn al geïmplementeerd.

## Overzicht

Dit document biedt een stap-voor-stap plan om de extensibility gaps op te lossen vóór v2.1.

### Afbakening voor dagafsluiting

De volledige dagafsluiting wordt in deze roadmap nog niet functioneel uitgewerkt. Die stap komt pas nadat de leeslaag, scoreverwerking en statusovergangen voldoende stabiel zijn. Voor een latere valide dagafsluiting zijn in elk geval nodig:

- definitieve tussenstand- en eindstandberekening
- read-only afscherming na sluiting
- certificaatgeneratie op basis van afgeronde resultaten
- logging van afsluit- en publicatiestatus
- consistente koppeling met repository- en service-laag

Dit document houdt die stap bewust open, zodat de uiteindelijke dagafsluiting later vanuit de werkende kernlogica kan worden opgesteld.

Voor de tussentijd is de voorbereidende checklist uitgewerkt in [Dagafsluiting_Voorbereiding.md](Dagafsluiting_Voorbereiding.md). Dat document beschrijft alleen de preconditions en voorbereiding; de echte afsluitflow blijft daar expliciet buiten.

**Timeline:** 3-4 maanden (parallel werk op meerdere fronten mogelijk)

**Prioriteit:**
1. 🔴 **Kritiek** – Blokkeert v2.1 roadmap, start ASAP
2. 🟠 **Hoog** – Aanbevolen vóór productie
3. 🟡 **Gemiddeld** – Roadmap items
4. 🟢 **Laag** – Future nice-to-have

---

## Fase 1: Datamodel Extensie (Week 1–2) 🔴 KRITIEK

### Doel
Voeg flexibiliteit toe zodat toekomstige velden zonder migraties kunnen worden opgeslagen.

### Stap 1.1: JSON meta_data kolommen toevoegen

**Wat:** Voeg JSON-veld toe aan Event, Team, Part, Assignment voor flexibele toekomstige data.

**Waarom:** 
- Geen database-migration nodig voor nieuwe velden
- Extensies kunnen custom data opslaan
- Backwards-compatible

**Hoe implementeren:**

1. **Create migration file:**
   ```
   database/migrations/YYYY-MM-DD-add-meta-data-fields.php
   ```

2. **Migration content:**
   ```php
   <?php
   namespace BSO\Survival\Database\Migrations;
   
   class AddMetaDataFields {
       public function up() {
           global $wpdb;
           $charset = $wpdb->get_charset_collate();
           
           // Tabel event
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_events 
               ADD COLUMN meta_data JSON DEFAULT '{}' AFTER status");
           
           // Tabel team
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_teams 
               ADD COLUMN meta_data JSON DEFAULT '{}' AFTER status");
           
           // Tabel part (onderdeel)
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_parts 
               ADD COLUMN meta_data JSON DEFAULT '{}' AFTER status");
           
           // Tabel assignment
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_assignments 
               ADD COLUMN meta_data JSON DEFAULT '{}' AFTER status");
       }
       
       public function down() {
           global $wpdb;
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_events DROP COLUMN meta_data");
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_teams DROP COLUMN meta_data");
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_parts DROP COLUMN meta_data");
           $wpdb->query("ALTER TABLE {$wpdb->prefix}bso_survival_assignments DROP COLUMN meta_data");
       }
   }
   ```

3. **Update FO datamodel sectie:**
   - Voeg toe aan Event entity: `json meta_data`
   - Voeg toe aan Team entity: `json meta_data`
   - Voeg toe aan Part entity: `json meta_data`
   - Voeg toe aan Assignment entity: `json meta_data`

4. **Validatiechecklist:**
   - [ ] Migration reversible (down() werkt)
   - [ ] Kolommen hebben default `{}`
   - [ ] Geen bestaande data verloren
   - [ ] Indexes opnieuw gebouwd

### Stap 1.2: Meta-data accessor helper-klasse creëren

**Wat:** Maak hulpklasse voor veilige meta_data opslag/retrieval.

**File:** `src/Support/MetaDataHelper.php`

```php
<?php
namespace BSO\Survival\Support;

class MetaDataHelper {
    /**
     * Get meta value from entity
     */
    public static function get($entity, $key, $default = null) {
        if (!isset($entity->meta_data)) {
            return $default;
        }
        $data = json_decode($entity->meta_data, true) ?: [];
        return $data[$key] ?? $default;
    }
    
    /**
     * Set meta value on entity
     */
    public static function set(&$entity, $key, $value) {
        $data = json_decode($entity->meta_data ?? '{}', true) ?: [];
        $data[$key] = $value;
        $entity->meta_data = json_encode($data);
        return $entity;
    }
    
    /**
     * Merge meta values
     */
    public static function merge(&$entity, array $updates) {
        $data = json_decode($entity->meta_data ?? '{}', true) ?: [];
        $data = array_merge($data, $updates);
        $entity->meta_data = json_encode($data);
        return $entity;
    }
    
    /**
     * Delete meta key
     */
    public static function delete(&$entity, $key) {
        $data = json_decode($entity->meta_data ?? '{}', true) ?: [];
        unset($data[$key]);
        $entity->meta_data = json_encode($data);
        return $entity;
    }
}
```

**Validatiechecklist:**
- [ ] Unit tests geschreven voor alle methodes
- [ ] Error handling voor malformed JSON
- [ ] Documentatie in code

---

## Fase 2: Scoring Method Plugin-Patroon (Week 2–3) 🔴 KRITIEK

### Doel
Maak scoremethodes pluggable zodat toekomstig eigen scoremethodes kunnen worden toegevoegd zonder core-wijziging.

### Stap 2.1: ScoringMethodInterface creëren

**File:** `src/Contracts/ScoringMethodInterface.php`

```php
<?php
namespace BSO\Survival\Contracts;

interface ScoringMethodInterface {
    /**
     * Get method identifier
     */
    public function get_id(): string;
    
    /**
     * Get human-readable name
     */
    public function get_name(): string;
    
    /**
     * Get description for admin
     */
    public function get_description(): string;
    
    /**
     * Validate raw input value
     */
    public function validate_raw_value($value, $config): bool;
    
    /**
     * Normalize raw value to standard points (0-100)
     * 
     * @param mixed $raw_value The raw input (time in seconds, points, distance in meters)
     * @param array $config Method-specific config (max_time, max_distance, etc.)
     * @return float Normalized points 0-100
     */
    public function normalize_to_points($raw_value, array $config): float;
    
    /**
     * Generate position proposal for all teams
     * 
     * @param array $team_scores Array of [team_id => normalized_points]
     * @return array [team_id => position] where position 1 = best
     */
    public function generate_position_proposal(array $team_scores): array;
    
    /**
     * Get UI field type for admin (text, number, time, distance, etc.)
     */
    public function get_field_type(): string;
    
    /**
     * Get field unit label (seconds, points, meters)
     */
    public function get_field_unit(): string;
}
```

### Stap 2.2: Registry-klasse creëren

**File:** `src/Services/ScoringMethodRegistry.php`

```php
<?php
namespace BSO\Survival\Services;

use BSO\Survival\Contracts\ScoringMethodInterface;
use BSO\Survival\Services\ScoringMethods\TimeScoringMethod;
use BSO\Survival\Services\ScoringMethods\PointsScoringMethod;
use BSO\Survival\Services\ScoringMethods\DistanceScoringMethod;

class ScoringMethodRegistry {
    private static $methods = [];
    
    /**
     * Register scoring method
     */
    public static function register($id, ScoringMethodInterface $method): void {
        self::$methods[$id] = $method;
    }
    
    /**
     * Get registered method
     */
    public static function get($id): ?ScoringMethodInterface {
        return self::$methods[$id] ?? null;
    }
    
    /**
     * Get all registered methods
     */
    public static function all(): array {
        return self::$methods;
    }
    
    /**
     * Check if method exists
     */
    public static function exists($id): bool {
        return isset(self::$methods[$id]);
    }
    
    /**
     * Initialize default methods
     */
    public static function init_defaults(): void {
        self::register('time', new TimeScoringMethod());
        self::register('points', new PointsScoringMethod());
        self::register('distance', new DistanceScoringMethod());
        
        // Allow plugins to register custom methods
        do_action('bso_survival_register_scoring_methods', self::class);
    }
}
```

### Stap 2.3: Implementeer de drie standaard-methodes

**File:** `src/Services/ScoringMethods/TimeScoringMethod.php`

```php
<?php
namespace BSO\Survival\Services\ScoringMethods;

use BSO\Survival\Contracts\ScoringMethodInterface;

class TimeScoringMethod implements ScoringMethodInterface {
    public function get_id(): string {
        return 'time';
    }
    
    public function get_name(): string {
        return 'Tijd (seconden)';
    }
    
    public function get_description(): string {
        return 'Snelste tijd wint. Team met laagste tijd krijgt meeste punten.';
    }
    
    public function validate_raw_value($value, $config): bool {
        return is_numeric($value) && $value >= 0;
    }
    
    public function normalize_to_points($raw_value, array $config): float {
        // Lower time = higher score
        // Formula: 100 * (max_time - actual_time) / max_time
        $max_time = $config['max_time'] ?? 1200; // default 20 minutes
        if ($raw_value <= 0 || $raw_value > $max_time) {
            return 0;
        }
        return (100 * ($max_time - $raw_value)) / $max_time;
    }
    
    public function generate_position_proposal(array $team_scores): array {
        // Sort descending (higher score = better position = lower position number)
        arsort($team_scores);
        $positions = [];
        $rank = 1;
        foreach ($team_scores as $team_id => $score) {
            $positions[$team_id] = $rank++;
        }
        return $positions;
    }
    
    public function get_field_type(): string {
        return 'time';
    }
    
    public function get_field_unit(): string {
        return 'seconden';
    }
}
```

**Soortgelijk voor PointsScoringMethod en DistanceScoringMethod.**

### Stap 2.4: Hook voor custom scoring-methodes

**In plugin bootstrap:**

```php
// Initialize registry with defaults
ScoringMethodRegistry::init_defaults();

// Allow plugins to register custom methods
add_action('bso_survival_register_scoring_methods', function() {
    // Example: Custom technique scoring method
    // ScoringMethodRegistry::register('technique', new TechniqueScoringMethod());
});
```

### Stap 2.5: Update PartRule datamodel

**In FO Datamodel sectie (sectie 5):**

```
PART_RULE {
  int id PK
  int part_id FK
  string scoring_mode         # 'time', 'points', 'distance' (links to registry)
  json scoring_config         # Method-specific parameters
  string unit                 # Unit label (seconds, points, meters)
  string tiebreaker_mode      # How to break ties
}
```

**Voorstel JSON structure voor scoring_config:**
```json
{
  "max_time": 1200,
  "max_distance": 500,
  "max_points": 100,
  "normalization_curve": "linear"
}
```

### Stap 2.6: Update FO score-berekening sectie

**In sectie 11 (Berekeningen):**

Vervang hardcoded beschrijving door:

```
Scoreberekening per scoremethode

Per onderdeel is een scoremethode geconfigureerd (tijd, punten, afstand of custom).
Het systeem roept de registreerde ScoringMethod-implementatie aan:

1. Valideer ruwe invoer (validate_raw_value)
2. Normaliseer naar standaard schaal 0-100 (normalize_to_points)
3. Genereer positie-voorstel op basis van genormaliseerde scores (generate_position_proposal)
4. Scheidsrechter bevestigt of corrigeert volgorde
5. Omzet positie naar rankpunten via formule

Toekomstige eigen scoremethodes kunnen worden geregistreerd via:
add_action('bso_survival_register_scoring_methods', function() {
    ScoringMethodRegistry::register('custom_id', new CustomScoringMethod());
});
```

### Validatiechecklist:
- [ ] Interface gedefinieerd en gedocumenteerd
- [ ] Registry-klasse geschreven met init_defaults
- [ ] Drie standaard-methodes geïmplementeerd
- [ ] Unit tests voor elke methode
- [ ] Bootstrap hook ingesteld
- [ ] FO bijgewerkt
- [ ] Admin UI aangepast (dropdown van registry)

---

## Fase 3: Dashboard Widget-Architectuur (Week 3–4) 🔴 KRITIEK

### Doel
Maak dashboard modulariseerbaar zodat custom widgets kunnen worden toegevoegd.

### Stap 3.1: WidgetInterface creëren

**File:** `src/Contracts/DashboardWidgetInterface.php`

```php
<?php
namespace BSO\Survival\Contracts;

interface DashboardWidgetInterface {
    /**
     * Get widget identifier
     */
    public function get_id(): string;
    
    /**
     * Get display title
     */
    public function get_title(): string;
    
    /**
     * Get widget position/priority
     */
    public function get_priority(): int;
    
    /**
     * Get required capabilities to view
     */
    public function get_capabilities(): array;
    
    /**
     * Get data for rendering
     */
    public function get_data($event_id, $filters = []): array;
    
    /**
     * Render HTML output
     */
    public function render($context): string;
    
    /**
     * Get JS dependencies (handles)
     */
    public function get_script_dependencies(): array;
    
    /**
     * Get CSS dependencies (handles)
     */
    public function get_style_dependencies(): array;
}
```

### Stap 3.2: Dashboard Widget Registry

**File:** `src/Services/DashboardWidgetRegistry.php`

```php
<?php
namespace BSO\Survival\Services;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class DashboardWidgetRegistry {
    private static $widgets = [];
    private static $sections = [];
    
    /**
     * Register widget
     */
    public static function register($section, DashboardWidgetInterface $widget): void {
        if (!isset(self::$widgets[$section])) {
            self::$widgets[$section] = [];
        }
        self::$widgets[$section][$widget->get_id()] = $widget;
    }
    
    /**
     * Get all widgets for section
     */
    public static function get_section($section): array {
        return self::$widgets[$section] ?? [];
    }
    
    /**
     * Get widget by ID
     */
    public static function get($section, $id): ?DashboardWidgetInterface {
        return self::$widgets[$section][$id] ?? null;
    }
    
    /**
     * Render entire section
     */
    public static function render_section($section, $event_id, $filters = []): string {
        $widgets = self::get_section($section);
        usort($widgets, fn($a, $b) => $a->get_priority() <=> $b->get_priority());
        
        $html = "<div class='dashboard-section dashboard-section-{$section}'>";
        foreach ($widgets as $widget) {
            if (current_user_can($widget->get_capabilities())) {
                $context = ['event_id' => $event_id, 'data' => $widget->get_data($event_id, $filters)];
                $html .= $widget->render($context);
            }
        }
        $html .= "</div>";
        
        return $html;
    }
}
```

### Stap 3.3: Standaard dashboard-widgets creëren

**File structure:**
```
src/Widgets/
├── TimeslotProgressWidget.php
├── TeamRankingWidget.php
├── ReportingStatusWidget.php
├── MessageWidget.php
├── ContactFinderWidget.php
└── FallbackScoreWidget.php
```

**Voorbeeld: TimeslotProgressWidget.php**

```php
<?php
namespace BSO\Survival\Widgets;

use BSO\Survival\Contracts\DashboardWidgetInterface;

class TimeslotProgressWidget implements DashboardWidgetInterface {
    public function get_id(): string {
        return 'timeslot_progress';
    }
    
    public function get_title(): string {
        return 'Tijdslot voortgang';
    }
    
    public function get_priority(): int {
        return 10; // Render first
    }
    
    public function get_capabilities(): array {
        return ['read_bso_survival_event']; // All users
    }
    
    public function get_data($event_id, $filters = []): array {
        // Query timeslots for this event
        return [
            'current_timeslot' => 5,
            'total_timeslots' => 12,
            'start_time' => '09:00',
            'end_time' => '15:55',
            'percent_complete' => 42
        ];
    }
    
    public function render($context): string {
        $data = $context['data'];
        ob_start();
        ?>
        <div class="widget widget-timeslot-progress">
            <h3><?php echo $this->get_title(); ?></h3>
            <div class="progress-bar">
                <div class="progress" style="width: <?php echo $data['percent_complete']; ?>%"></div>
            </div>
            <p>Ronde <?php echo $data['current_timeslot']; ?> van <?php echo $data['total_timeslots']; ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function get_script_dependencies(): array {
        return []; // No JS needed
    }
    
    public function get_style_dependencies(): array {
        return ['bso-survival-dashboard'];
    }
}
```

### Stap 3.4: Bootstrap widgets in plugin initialization

**In plugin bootstrap:**

```php
use BSO\Survival\Services\DashboardWidgetRegistry;
use BSO\Survival\Widgets\TimeslotProgressWidget;
use BSO\Survival\Widgets\TeamRankingWidget;
// ... etc

// Register default widgets
DashboardWidgetRegistry::register('main', new TimeslotProgressWidget());
DashboardWidgetRegistry::register('main', new TeamRankingWidget());
DashboardWidgetRegistry::register('main', new ReportingStatusWidget());

// Allow plugins to register custom widgets
do_action('bso_survival_dashboard_widgets_init', DashboardWidgetRegistry::class);
```

### Stap 3.5: Update FO Dashboard sectie

**In sectie 8 (Dashboard en mobiele ervaring):**

Vervang hardcoded dashboard-beschrijving door:

```
Dashboard Widget-architectuur

Het dashboard bestaat uit modulaire widgets die via registry worden beheerd.

Standaard widgets:
- Tijdslotverloopdiagram (TimeslotProgressWidget)
- Teampositieoverzicht (TeamRankingWidget)
- Onderdeel-rapportagestatus (ReportingStatusWidget)
- Meldingen (MessageWidget)
- Contactzoeker (ContactFinderWidget)
- Fallback-scoreinvoer (FallbackScoreWidget) – alleen leiding

Custom widgets toevoegen:

add_action('bso_survival_dashboard_widgets_init', function() {
    DashboardWidgetRegistry::register('main', new CustomAnalyticsWidget());
});

Elke widget implementeert DashboardWidgetInterface met:
- get_id(), get_title(), get_priority()
- get_data() – haalt data op
- render() – genereert HTML
- get_capabilities() – toegangscontrole
- get_script/style_dependencies() – asset management
```

### Validatiechecklist:
- [x] Interface gedefinieerd
- [x] Registry-klasse geschreven
- [x] Zes standaard-widgets geïmplementeerd
- [x] Unit tests voor widgets
- [x] Bootstrap ingesteld
- [x] FO bijgewerkt

---

## Fase 4: WordPress Hooks & Filters Strategy (Week 4) 🟠 HOOG

### Doel
Definieer kernhooks zodat plugins kunnen inhaken op kritieke momenten.

### Stap 4.1: Hook-registratie document creëren

**File:** `docs/hooks-and-filters.md`

```markdown
# BSO Survival Hooks & Filters

## Action Hooks (do_action)

### bso_survival_event_status_changed
Fired when event status transitions (concept → planned → active → closed).

```php
do_action('bso_survival_event_status_changed', $event_id, $old_status, $new_status);
```

**Parameters:**
- $event_id (int) – Event ID
- $old_status (string) – Previous status
- $new_status (string) – New status

**Example:**
```php
add_action('bso_survival_event_status_changed', function($event_id, $old_status, $new_status) {
    if ($new_status === 'active') {
        // Event started
    }
});
```

### bso_survival_before_score_validation
Fired before score validation.

```php
do_action('bso_survival_before_score_validation', $score_entry);
```

### bso_survival_score_recorded
Fired after score successfully recorded.

```php
do_action('bso_survival_score_recorded', $score_entry_id, $assignment_id, $raw_value);
```

### bso_survival_ranking_updated
Fired after tussenstand is recalculated.

```php
do_action('bso_survival_ranking_updated', $event_id, $standings);
```

### bso_survival_certificate_generated
Fired after certificate is generated.

```php
do_action('bso_survival_certificate_generated', $certificate_id, $team_id, $file_path);
```

### bso_survival_register_scoring_methods
Fired during scoring method registry initialization.

```php
do_action('bso_survival_register_scoring_methods', ScoringMethodRegistry::class);
```

### bso_survival_dashboard_widgets_init
Fired during dashboard widget registry initialization.

```php
do_action('bso_survival_dashboard_widgets_init', DashboardWidgetRegistry::class);
```

## Filter Hooks (apply_filters)

### bso_survival_score_normalized_points
Filter normalized score before saving.

```php
$normalized = apply_filters('bso_survival_score_normalized_points', $normalized, $raw_value, $scoring_method);
```

### bso_survival_position_proposal
Filter position proposal before referee review.

```php
$positions = apply_filters('bso_survival_position_proposal', $positions, $assignment_id);
```

### bso_survival_ranking_query
Filter ranking calculation.

```php
$standing = apply_filters('bso_survival_ranking_query', $standing, $event_id);
```

### bso_survival_certificate_content
Filter certificate HTML/content.

```php
$content = apply_filters('bso_survival_certificate_content', $content, $team_id, $event_id);
```
```

### Stap 4.2: Hooks in code invoegen

**Kritieke plaatsen:**

1. **Score entry submission** (ScoringService):
   ```php
   do_action('bso_survival_before_score_validation', $score_entry);
   // ... validation
   do_action('bso_survival_score_recorded', $score_entry->id, $assignment_id, $raw_value);
   ```

2. **Ranking recalculation** (RankingService):
   ```php
   $standings = $this->calculate_standings($event_id);
   do_action('bso_survival_ranking_updated', $event_id, $standings);
   ```

3. **Event status change** (EventService):
   ```php
   do_action('bso_survival_event_status_changed', $event->id, $old_status, $new_status);
   ```

4. **Certificate generation** (CertificateService):
   ```php
   do_action('bso_survival_certificate_generated', $certificate_id, $team_id, $file_path);
   ```

### Validatiechecklist:
- [ ] Hooks-document geschreven en in docs/ opgeslagen
- [ ] Alle hooks in code ingevoegd
- [ ] Hook-parameters gedocumenteerd
- [ ] Voorbeelden gegeven per hook
- [ ] FO bijgewerkt met hooks-verwijzing

---

## Fase 5: Notification Channel Abstraction (Week 5) 🟠 HOOG

### Doel
Abstraheer communicatie zodat SMS/push later kan worden toegevoegd.

### Stap 5.1: NotificationChannelInterface creëren

**File:** `src/Contracts/NotificationChannelInterface.php`

```php
<?php
namespace BSO\Survival\Contracts;

interface NotificationChannelInterface {
    /**
     * Send notification
     */
    public function send($recipient, $subject, $body, array $context = []): bool;
    
    /**
     * Get channel ID
     */
    public function get_id(): string;
    
    /**
     * Get channel name
     */
    public function get_name(): string;
    
    /**
     * Check if channel is configured
     */
    public function is_available(): bool;
}
```

### Stap 5.2: EmailChannel implementeren

**File:** `src/Services/NotificationChannels/EmailChannel.php`

```php
<?php
namespace BSO\Survival\Services\NotificationChannels;

use BSO\Survival\Contracts\NotificationChannelInterface;

class EmailChannel implements NotificationChannelInterface {
    public function send($recipient, $subject, $body, array $context = []): bool {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($recipient, $subject, $body, $headers);
    }
    
    public function get_id(): string {
        return 'email';
    }
    
    public function get_name(): string {
        return 'E-mail';
    }
    
    public function is_available(): bool {
        return true; // Always available
    }
}
```

### Stap 5.3: Notification Manager

**File:** `src/Services/NotificationManager.php`

```php
<?php
namespace BSO\Survival\Services;

use BSO\Survival\Contracts\NotificationChannelInterface;

class NotificationManager {
    private static $channels = [];
    
    public static function register_channel(NotificationChannelInterface $channel): void {
        self::$channels[$channel->get_id()] = $channel;
    }
    
    public static function send($channel_id, $recipient, $subject, $body, array $context = []): bool {
        if (!isset(self::$channels[$channel_id])) {
            return false;
        }
        return self::$channels[$channel_id]->send($recipient, $subject, $body, $context);
    }
    
    public static function send_all($recipient, $subject, $body, array $context = []): array {
        $results = [];
        foreach (self::$channels as $channel_id => $channel) {
            if ($channel->is_available()) {
                $results[$channel_id] = $channel->send($recipient, $subject, $body, $context);
            }
        }
        return $results;
    }
}
```

### Validatiechecklist:
- [ ] Interface gedefinieerd
- [ ] EmailChannel geïmplementeerd
- [ ] NotificationManager geschreven
- [ ] Bootstrap ingesteld
- [ ] FO bijgewerkt

---

## Fase 6: REST API Versioning Strategie (Week 5–6) 🟠 HOOG

### Doel
Maak REST API toekomstvast met versioning en filtering.

### Subfase 6.1: Admin score-invoer en direct bewerken

Statusupdate 8 juli 2026:
- admin submenu Score Invoer toegevoegd onder Survival
- admin REST endpoints toegevoegd: `POST /scores/entries` en `PATCH /scores/entries/{score_entry_id}`
- service-orkestratie toegevoegd voor validatie, read-only blokkade, auditlog en ranking-refresh
- score repository uitgebreid met find/update voor correctiestroom

Doel:
- Beheerders kunnen direct scores invoeren en corrigeren vanuit de adminomgeving.

Scope:
- adminformulier voor score-invoer per assignment
- adminformulier voor scorebewerking (update op bestaande entry)
- validatie op part rule + scoremethode
- audit logging op create/update
- herberekening/ranking-refresh na wijziging

Technische ingangen:
- nieuwe admin REST-endpoints onder `/wp-json/bso-survival/v1/scores/...`
- nonce + capability (`manage_options` of fijnmaziger score-capability)
- service-laag gebruikt `ScoreEntryService` + `RankingService` + `AuditLogService`

Minimum endpointset:
- `POST /scores/entries` (nieuwe score)
- `PATCH /scores/entries/{score_entry_id}` (scorecorrectie)
- `POST /scores/recalculate` (geforceerde herberekening voor part binnen event)

UI-plaatsing:
- submenu onder BSO Rules: "Score Invoer"
- filtering op event -> timeslot -> assignment
- inline validatiefeedback en opslaan zonder full reload

Validatiechecklist 6.1:
- [x] Adminformulier kan nieuwe score opslaan
- [x] Adminformulier kan bestaande score bewerken
- [x] Hooks `bso_survival_before_score_validation` en `bso_survival_score_recorded` worden afgevuurd
- [x] Ranking update wordt aangeroepen na scorewijziging
- [x] Audit log bevat score create/update acties
- [x] Permission + nonce checks aanwezig

#### 6.1.A Beheer-meldingen voor centraal dashboard

Statusupdate 8 juli 2026:
- admin submenu Dashboard Meldingen toegevoegd onder Survival
- DashboardMessageService en repository toegevoegd met create + statuswissel
- MessageWidget leest nu actieve event-meldingen met fallback op statische tekst
- datamodel gebruikt voorlopig bestaande tabel `bso_survival_messages` (verdere velduitbreiding blijft open)
- prioriteitssortering geactiveerd via severity/type mapping (`urgent > warning > info > success`)
- event + global filtering actief via scope (`event`, `global`, `all`) in admin, service, repository en REST
- PHPUnit-dekking toegevoegd voor message service- en controllerpaden

Doel:
- Beheerders kunnen zelf meldingen plaatsen, plannen en intrekken voor het centrale dashboard.

Huidige situatie (baseline):
- Weergave bestaat al via `MessageWidget`, maar zonder admin-invoer of persistente opslag van vrije meldingen.

Uitbreiding scope:
- nieuw admin-submenu: "Dashboard Meldingen"
- CRUD voor meldingen (aanmaken, bewerken, deactiveren, verwijderen)
- prioriteit en zichtbaarheid per event
- optionele geldigheid (`visible_from`, `visible_until`)
- audit logging op create/update/delete

Datamodel (besloten in F6-01):
- canonical tabel: `bso_survival_messages` (zie `docs/adr/ADR-0001-message-storage-model.md`)
- huidige velden:
    - `id` (PK)
    - `event_id`
    - `type`
    - `text`
    - `visibility`
    - `status`
    - `meta_data` (JSON-string)
    - `created_at`, `updated_at`
- geplande additieve uitbreiding (zonder tabelmigratie):
    - `visible_from` (datetime nullable)
    - `visible_until` (datetime nullable)

REST-contract (admin):
- `GET /wp-json/bso-survival/v1/dashboard/messages`
- `POST /wp-json/bso-survival/v1/dashboard/messages`
- `PATCH /wp-json/bso-survival/v1/dashboard/messages/{message_id}`
- `DELETE /wp-json/bso-survival/v1/dashboard/messages/{message_id}`
- `POST /wp-json/bso-survival/v1/dashboard/messages/{message_id}/activate`
- `POST /wp-json/bso-survival/v1/dashboard/messages/{message_id}/deactivate`

Business rules:
- Alleen gebruikers met dashboard-manage capability mogen muteren.
- `visible_until` moet groter zijn dan `visible_from` indien beide gezet zijn.
- Alleen actieve en geldige meldingen worden gerenderd in het centrale dashboard.
- Sortering: `priority DESC`, daarna `updated_at DESC`.

Integratie met widgets:
- `MessageWidget` leest meldingen via nieuwe `DashboardMessageService`.
- Fallback op bestaande statische tekst alleen als er geen actieve meldingen zijn.
- Widget-output krijgt severity CSS classes voor visuele nadruk.

Validatiechecklist 6.1.A:
- [x] Admin kan melding aanmaken met type/tekst/severity
- [x] Admin kan melding aan/uit zetten zonder verwijderen
- [x] Event-specifieke en globale meldingen worden correct gefilterd
- [x] MessageWidget toont prioriteit-volgorde correct
- [x] Read-only eventstatus blokkeert score-invoer, maar niet message-beheer in admin
- [x] PHPUnit dekt repository/service/controller paden voor messages

#### 6.1.B Vrijwilliger-aanmeldscherm + teaminschrijving

Doel:
- Vrijwilliger (ouder) kan een team inschrijven met teamnaam, contactgegevens en teamleden.
- Beheerder ziet in admin direct de inschrijfstand: `ingeschreven / max_teams`.
- Dashboard toont een teller zodra inschrijvingen volledig zijn.

Controle huidige codebasis (feitelijke status):
- Frontend aanmeldscherm is aanwezig via shortcode `bso_survival_team_registration`.
- Registratie-submit loopt via `POST /wp-json/bso-survival/v1/registrations`.
- Teamlaag ondersteunt registratie-opslag inclusief teamleden (`TeamRegistrationService` + repositories).
- Open/gesloten registratie-window wordt afgedwongen via `RegistrationWindowService`.
- Dashboard en admin tonen registratievoortgang (`x / max_teams`) inclusief VOL-status.

Technisch ontwerp 6.1.B:

Nieuwe frontend ingang:
- shortcode: `bso_survival_team_registration`
- controller: `src/Frontend/TeamRegistrationController.php`
- template: `templates/frontend-team-registration.php`
- script: `assets/js/bso-survival-team-registration.js`

REST-contract registratie:
- `POST /wp-json/bso-survival/v1/registrations`
- payload:
    - `event_id`
    - `team_name`
    - `contact_name`
    - `contact_email`
    - `contact_phone`
    - `team_members` (array met minimaal 1 naam)
- response:
    - `registration_id`
    - `team_id`
    - `status`
    - `counts` (`registered_teams`, `max_teams`)

Service/repository uitbreidingen:
- `TeamRepositoryInterface` uitbreiden met:
    - `create(array $teamData)`
    - `findByEventIdAndName(int $eventId, string $name)`
- nieuwe `TeamMemberRepository` voor bulk insert teamleden
- nieuwe `RegistrationWindowService` voor open/gesloten periode check
- nieuwe `TeamRegistrationService` die volledige flow orkestreert

Validatieregels registratie:
- inschrijven alleen binnen geopende inschrijfperiode
- unieke teamnaam per event
- geldig email-formaat en minimaal 1 teamlid
- max team-capaciteit respecteren (`registered_teams < max_teams`)
- duplicate-submit bescherming via nonce + idempotency key

Admin teller ontwerp:
- nieuw admin-submenu: "Inschrijvingen"
- overzicht per event:
    - `Ingeschreven teams: x / max_teams`
    - `% bezetting`
    - status inschrijfperiode (open/gesloten)
- bron:
    - `x` uit `TeamRepository::countByEventId(event_id)`
    - `max_teams` uit event `meta_data.max_teams`

Dashboard teller ontwerp:
- `DashboardOverviewService` uitbreiden met:
    - `counts.registered_teams`
    - `counts.max_teams`
    - `status.is_registration_full`
- nieuwe widget: `RegistrationCapacityWidget`
    - tekst: `x / max_teams` met actuele bezettingsindicator
    - badge voor status: `Open`, `Beschikbaar`, `VOL` of `Gesloten`
    - toont resterende capaciteit zodra `max_teams` beschikbaar is
    - opent bij beschikbare capaciteit een configurabele inschrijfpagina via dashboardnavigatie

Validatiechecklist 6.1.B:
- [x] Vrijwilliger kan team met leden succesvol inschrijven
- [x] Inschrijving blokkeert buiten open registratie-window
- [x] Admin ziet teller `x / max_teams` per event
- [x] Dashboard toont registratievoortgang en VOL-status
- [x] Team- en team_member records worden atomair opgeslagen
- [x] PHPUnit + integratietest dekken happy-path en foutpaden

#### 6.1.C Inschrijvingsbevestiging mail + beheerbare HTML-template

Doel:
- Na complete teaminschrijving ontvangt vrijwilliger automatisch een bevestigingsmail.
- Beheerder kan in admin een HTML-template beheren met slimme veldcodes.

Controle huidige mailstructuur (feitelijke status):
- In runtime-code zijn `EmailTemplateService`, `RegistrationConfirmationService` en admin templatebeheer aanwezig.
- Outbox-verzending met retry/backoff draait via `EmailOutboxService` + `OutboxProcessorService`.
- Conclusie stabiliteit: MVP-keten is operationeel; verdere hardening zit vooral in monitoring en rapportage.

Technisch ontwerp 6.1.C:

Datamodel:
- nieuwe tabel `bso_email_templates`
    - `id`, `template_key`, `subject`, `html_body`, `is_active`, `updated_by`, `updated_at`
- nieuwe tabel `bso_email_outbox`
    - `id`, `event_id`, `recipient`, `template_key`, `subject_snapshot`, `body_snapshot`, `status`, `attempt_count`, `next_attempt_at`, `last_error`, `dedupe_key`, `sent_at`, `created_at`, `updated_at`

Template veldcodes (MVP):
- `{vrijwilliger_naam}`
- `{team_naam}`
- `{event_naam}`
- `{event_datum}`
- `{aantal_teamleden}`
- `{inschrijf_id}`

Admin beheer:
- nieuw submenu: "Email Templates"
- editor met subject + HTML body + live preview
- lijst met beschikbare veldcodes en test-render voor voorbeelddata
- versiehistorie (minimaal laatste 5 versies) of auditlog op wijziging

Mail pipeline (robuust):
- registratieflow plaatst bericht in `bso_email_outbox` (geen directe hard-fail op wp_mail)
- worker/cron verwerkt outbox batchgewijs
- retries met backoff (bijv. 1m, 5m, 30m, 2h)
- idempotency: `dedupe_key` bevat (`template_key`, `team_id`, `recipient`) voor eenmalige bevestiging
- volledige logging in audit/outbox voor support en herverzending

Interface-laag (toekomstvast):
- `EmailTemplateRepositoryInterface`
- `EmailRendererInterface`
- `MailerInterface`
- `RegistrationConfirmationService`
- `OutboxProcessorService`

Stabiliteitsmaatregelen:
- strikte placeholder validatie (onbekende code -> expliciete foutmelding in admin)
- HTML sanitization bij opslaan (toegestane tags whitelist)
- tekst fallback (`text/plain`) naast HTML
- transactie rond registratie + outbox enqueue
- duidelijke monitoringstatistieken: queued/sent/failed

Validatiechecklist 6.1.C:
- [x] Beheerder kan HTML-template opslaan en previewen
- [x] Placeholder-resolutie werkt voor alle MVP veldcodes
- [x] Outbox bericht wordt aangemaakt na teaminschrijving
- [x] Cron/processor verstuurt mail en verwerkt retries
- [x] Dubbele bevestigingsmail wordt voorkomen via idempotency-regel
- [x] Fouten zijn zichtbaar in admin (status + last_error)

### Go/No-Go Gate voor start 6.1.B en 6.1.C

Doel van deze gate:
- Voorkomen dat inschrijving- en notificatieontwikkeling start op een nog veranderende publicatiebasis.

Volgorde (verplicht):
1. Rond openstaande closeout/publicatie-verankering af.
2. Bevries publicatiepayload contract.
3. Start pas daarna implementatie van 6.1.B en 6.1.C.

Gate A - Operationele triggerlaag gereed (uit vorige fase):
- [x] Er is een echte admin-UI of CLI-actie boven de bestaande closeout/publication REST-routes.
- [x] Beheerder kan event afsluiten/publiceren zonder handmatige losse API-calls.
- [x] Positieve en negatieve paden zijn functioneel getest.

Gate B - Publicatiepayload geconcretiseerd en stabiel:
- [x] Publicatie response bevat minimaal top-3 en volledige eindstandinformatie.
- [x] Veldnamen en datastructuur zijn gedocumenteerd als contract.
- [x] Contractwijzigingen zijn expliciet gelogd (changelog/roadmap).

Gate C - Kwaliteitsdrempel vóór communicatieflow:
- [x] PHPUnit suite groen op actuele branch.
- [x] Geen open blockers op eventstatus-overgangen (concept -> actief -> afgesloten -> gepubliceerd).
- [x] Audit logging op closeout/publicatie volledig actief.

Go/No-Go beslissing:
- GO: alle Gate A, B en C checklist-items zijn afgerond.
- NO-GO: minimaal 1 item open -> 6.1.B/6.1.C niet starten.

Executiebeleid na GO:
1. Start 6.1.B (vrijwilliger-aanmelding + teaminschrijving + tellers).
2. Start daarna 6.1.C (email-templatebeheer + bevestigingsmail outbox-flow).

Statusupdate 8 juli 2026:
- 6.1.B MVP geïmplementeerd en functioneel getest.
- 6.1.C MVP geïmplementeerd en functioneel getest.

### Subfase 6.2: Frontend scoreformulier (operationele invoer)

Statusupdate 8 juli 2026:
- frontend shortcode `bso_survival_score_form` toegevoegd met mobiele score-invoer
- REST endpoint `POST /bso-survival/v1/score-entries` toegevoegd voor frontend submit-flow
- read-only/publicatiestatus wordt server-side en client-side afgedwongen
- foutafhandeling verduidelijkt met valideerbare REST error-codes en gebruikersmeldingen
- PHPUnit unit coverage toegevoegd voor service- en REST-laag van score-invoer

Doel:
- Leiding/jury kan tijdens event scores invoeren via frontend/mobile zonder adminscherm.

Scope:
- shortcode of dedicated frontend route voor score-invoer
- rolgebaseerde toegang (geen publieke invoer)
- optimistic UI met duidelijke succes/foutmeldingen
- read-only blokkade wanneer eventstatus `afgesloten` of `gepubliceerd` is

Technische ingangen:
- frontend gebruikt dezelfde score-endpoints als subfase 6.1
- server-side validatie blijft leidend
- statuscheck via `DashboardOverviewService` flags (`is_read_only`, `is_published`)

Validatiechecklist 6.2:
- [x] Frontendformulier kan score invoeren voor geautoriseerde gebruiker
- [x] Frontendformulier weigert invoer bij read-only/publicatiestatus
- [x] Foutmeldingen zijn begrijpelijk bij invalid input
- [x] Ranking/dashboardweergave wordt na invoer consistent geactualiseerd
- [x] E2E test dekt volledige frontend submit-flow

### Relatie met bestaande Phase 6 API-taken

Bestaande versioning/response-standaardisatie blijft relevant en wordt na 6.1/6.2 doorgezet als:
- 6.3 API versioning plan
- 6.4 standard response wrapper

### Stap 6.3: API Versioning Plan

Statusupdate 8 juli 2026:
- `docs/api-versioning.md` toegevoegd als eerste versie van het versioning-plan

**File:** `docs/api-versioning.md`

```markdown
# BSO Survival REST API Versioning

## Current Version
v1 – MVP functionality (GET/POST basics)

## Versioning Strategy

Versioning is onderdeel van URL:
- /wp-json/bso-survival/v1/...
- /wp-json/bso-survival/v2/... (future)

### v1 – MVP (Current)
- Basic CRUD for events, teams, assignments
- Score submission
- Standings retrieval
- Messages

### v2 – Planned
- Batch operations
- Advanced filtering
- Sorting
- Pagination
- Webhook registration

### v3 – Future
- GraphQL option
- Real-time updates (WebSocket)

## Backwards Compatibility
- v1 endpoints remain stable
- Breaking changes only in new major version
- Deprecated endpoints marked with warning header
```

### Stap 6.4: Standard REST response wrapper

Statusupdate 8 juli 2026:
- `src/Support/ApiResponse.php` toegevoegd als centrale success/error helper
- nieuwe admin score REST endpoints gebruiken de wrapper

Statusupdate 8 juli 2026 (late update):
- REST controllers voor teamregistratie, event-closeout/publicatie, dashboard-layout en frontend score submit omgezet naar de centrale ApiResponse-wrapper
- paginering toegevoegd op `GET /bso-survival/v1/dashboard/messages` met `page` en `per_page` (plus legacy `limit` fallback)
- PHPUnit uitgebreid voor response-envelope en paginering
- filtering-voorbeelden toegevoegd in `docs/api-versioning.md`
- recalculate endpoint toegevoegd: `POST /bso-survival/v1/scores/recalculate`
- datamodel-upgrade messages: `meta_data` kolom toegevoegd aan schema + serviceondersteuning

**File:** `src/Support/ApiResponse.php`

```php
<?php
namespace BSO\Survival\Support;

class ApiResponse {
    public static function success(array $data = [], int $status = 200) {
        $payload = [
            'success' => true,
            'data' => $data
        ];

        $response = rest_ensure_response($payload);
        $response->set_status($status);

        return $response;
    }
    
    public static function error(string $code, string $message, int $status = 400, array $details = []) {
        return new \WP_Error($code, $message, [
            'status' => $status,
            'details' => $details,
        ]);
    }
    
    public static function paginated(array $items, int $total, int $page, int $perPage, array $meta = []) {
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;

        return self::success(array_merge($meta, [
            'items' => $items,
            'pagination' => [
                'page' => (int) $page,
                'per_page' => (int) $perPage,
                'total' => (int) $total,
                'total_pages' => $totalPages
            ]
        ]));
    }
}
```

### Validatiechecklist:
- [x] Versioning plan gedocumenteerd
- [x] ApiResponse-klasse geschreven
- [x] Alle endpoints consistent response-formaat gebruiken
- [x] Paginering werkend op list endpoints
- [x] Filtering examples in docs

---

## Voorstel: Implementatie-Volgorde & Timeline

### 💡 Aanbevolen Aanpak

**Optie A: Parallelle sprints (3-4 maanden total)**

```
Week 1–2:    Fase 1 + 2 (Datamodel + Scoring methods)
             Team: 1 developer + 1 tester

Week 2–3:    Fase 3 (Dashboard widgets)
             Team: 1 developer + 1 QA

Week 4:      Fase 4 (Hooks strategy) + Fase 5 (Notifications)
             Team: 1 developer (parallel)

Week 5–6:    Fase 6 (API versioning) + docs
             Team: 1 developer + documentation

Totaal effort: ~6 developer-weken
```

**Optie B: Sequentieel (meer stabiel, 5–6 maanden)**

```
Week 1–2:    Fase 1 (Datamodel) → Production validation
Week 3–4:    Fase 2 (Scoring methods)
Week 5–6:    Fase 3 (Dashboard widgets)
Week 7:      Fase 4 (Hooks) + Fase 5 (Notifications)
Week 8:      Fase 6 (API versioning)

Totaal effort: ~7 developer-weken (safer)
```

### 📋 My Recommendation: **Hybrid Aanpak**

1. **Start direct met Fase 1 + 2** (kritiek, blokkerende)
2. **Parallel:** Fase 4 (Hooks – lightweight) terwijl dev 1 aan Fase 2 werkt
3. **Volgende:** Fase 3 (Dashboard widgets – groter werk)
4. **Daarna:** Fase 5 + 6 (integratiewerk)

**Timeline: 4–5 maanden met 2 developers**

---

## Dingen die je kan DOEN

### Direct (deze week) 🚀

**Voor jou (als product owner/requirements):**

1. ✅ **FO document review:**
   - Lees Extensibility Review door
   - Identificeer welke gaps voor jou prioriteit zijn
   - Communiceer prioriteit naar dev-team

2. ✅ **Stakeholder alignment:**
   - Bespreek timeline met team
   - Alloceer resources (developers, testers)
   - Zet milestones in project-management tool

3. ✅ **Technical setup:**
   - Maak feature branches voor elk gap (feature/scoring-registry, feature/dashboard-widgets, etc.)
   - Setup testing framework (PHPUnit) als nog niet gedaan
   - Maak acceptance criteria voor elke fase

**Voor dev-team:**

1. ✅ **Fase 1 starten:**
   - Create migration file
   - Write MetaDataHelper class
   - Unit tests

2. ✅ **Fase 2 starten:**
   - Define ScoringMethodInterface
   - Build ScoringMethodRegistry
   - Implement TimeScoringMethod

### Komende weken 📅

**Week 1:**
- [ ] Datamodel migration merged en getest
- [ ] Meta-data helper klasse reviewed
- [ ] Scoring method interface accepted

**Week 2:**
- [ ] Eerste scoremethode geïmplementeerd
- [ ] Dashboard widget interface draft
- [ ] Hooks list in docs

**Week 3:**
- [ ] Alle scoremethodes geïmplementeerd
- [ ] Dashboard widgets functional
- [ ] All hooks in code

**Week 4:**
- [ ] Notification channel abstraction done
- [ ] REST API versioning plan finalized
- [ ] FO document fully updated

### Voorstel: Testing Strategy

**Per fase:**

1. **Unit tests:**
   - ScoringMethodInterface implementations
   - MetaDataHelper methods
   - Registry classes

2. **Integration tests:**
   - Score submission with new method
   - Dashboard rendering with widgets
   - Hook firing on events

3. **Manual tests:**
   - Admin UI works with new settings
   - Mobile dashboard with new widgets
   - Extension development (write test plugin)

---

## Next Steps: Wat nu?

### 1️⃣ Prioriteit bepalen

Welke gaps zijn voor jou MOST critical?
- [ ] Scoring method plugin-patroon (custom scoring later)
- [ ] Dashboard widgets (custom dashboards later)
- [ ] Meta-data flexibility (unknown future needs)
- [ ] Hooks/filters (extensibility foundation)
- [ ] API versioning (third-party integrations)

### 2️⃣ Resource allocation

Hoeveel developers/testers beschikbaar?
- [ ] 1 developer (voer alles sequentieel uit)
- [ ] 2 developers (kan parallel werken)
- [ ] 3+ developers (aggressieve timeline mogelijk)

### 3️⃣ Timeline bespreken

Wanneer moet v2 productie-klaar zijn?
- [ ] ASAP (parallel sprints nodig)
- [ ] Over 4–5 maanden (comfortabel)
- [ ] Flexibel (phased rollout van features)

### 4️⃣ Start eerste fase

Volg Fase 1 (Datamodel) step-by-step:
- Create migration → test locally → merge
- Write MetaDataHelper → unit tests → review
- Update FO → accept → commit

---

## Support

Heb je vragen bij implementatie? Ik kan helpen met:
- Concrete code-reviews
- Bug fixing in implementatie
- FO-updates per milestone
- Test-writing strategies
- Architecture decisions

*Opgesteld op 7 juli 2026 · BSO Survival v2 Implementation Roadmap*
