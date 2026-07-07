# Dev Team Onboarding – BSO Survival v2 Extensibility Implementation

**Document Version:** 1.0  
**Created:** 7 July 2026  
**Target Audience:** Development team implementing Phases 1–6  
**Duration:** 1–2 days orientation + then ongoing reference

## Actuele status (7 juli 2026)

Sinds de oorspronkelijke Fase 1 onboarding is extra functionaliteit opgeleverd:
- read-only frontend shortcodes voor dashboard, onderdelen en teams
- gecombineerde shortcodes voor volledig en compact eventoverzicht
- robuuste foutafhandeling met admin notice bij ongeldig event_id
- testsuite groen: 72/72

Belangrijke referenties:
- [src/Frontend/ShortcodeController.php](../src/Frontend/ShortcodeController.php)
- [src/Frontend/EventOverviewController.php](../src/Frontend/EventOverviewController.php)
- [src/Frontend/EventSummaryController.php](../src/Frontend/EventSummaryController.php)
- [docs/hooks-and-filters.md](hooks-and-filters.md)

---

## 📋 Quick Start (First 2 Hours)

### What You're Building
BSO Survival v2 is a **WordPress plugin for survival event management**. You're adding **extensibility layers** so toekomstige developers kunnen nieuwe features toevoegen zonder core-wijzigingen.

### Three Key Principles
1. **Modularity** – Each layer (Presentation, Application, Domain, Infrastructure) is independent
2. **Plugin-friendly** – Use WordPress hooks, filters, registries for extension points
3. **Clean Code** – Single responsibility, testable methods, documented behavior

### Your Role This Sprint
Implement **Fase 1: Datamodel & MetaDataHelper** to enable flexible data storage.

Voor huidig onderhoud van de shortcode-laag: volg eerst [docs/hooks-and-filters.md](hooks-and-filters.md) en draai daarna `./vendor/bin/phpunit`.

---

## 🏗️ Architecture Refresher

### Layered Design (from FO)
```
┌─────────────────────────────────────────────┐
│ PRESENTATION (Admin UI, Mobile, REST API)   │
├─────────────────────────────────────────────┤
│ APPLICATION (Planning, Scoring, Messaging)  │
├─────────────────────────────────────────────┤
│ DOMAIN (Algorithms, Business Logic)         │
├─────────────────────────────────────────────┤
│ INFRASTRUCTURE (Database, Hooks, Logging)   │
└─────────────────────────────────────────────┘
```

**Why?** Changes in one layer don't break others. Example: If scoring algorithm changes, REST API doesn't need updating.

### Key Entities (from Datamodel in FO)
- **Event** – One survival day (has many teams, parts, timeslots)
- **Team** – Group of 4–8 players (has members, receives scores)
- **Part** – Onderdeel/activity (has scoring rules, referee assignments)
- **Assignment** – Team + Part + Timeslot coupling per round
- **ScoreEntry** – Raw score, calculated rankpunten, position per assignment
- **JokerUsage** – Team's one-time joker on one part
- **Message** – Dashboard notification
- **Certificate** – Downloadable team result

---

## 📁 Project Structure

```
bso-survival/
├── src/
│   ├── Admin/                     # Admin pages, settings
│   ├── Core/                      # Plugin bootstrap, hooks setup
│   ├── Database/                  # Schema definitions
│   ├── Frontend/                  # Mobile dashboard templates
│   ├── Service/                   # Business logic (Scoring, Planning, etc.)
│   ├── Support/                   # Helpers (MetaDataHelper, etc.)
│   ├── Contracts/                 # Interfaces (ScoringMethod, Widget, etc.)
│   └── Widgets/                   # Dashboard widgets (future)
├── tests/
│   ├── Support/                   # Tests for Support classes
│   ├── Service/                   # Tests for Services
│   └── Feature/                   # Integration tests
├── database/
│   ├── schema.php                 # Table definitions
│   └── migrations/                # Future: migration files
├── docs/
│   ├── Functional_Design_v2.md   # Complete FO (your bible)
│   ├── hooks-and-filters.md      # Available hooks
│   ├── Extensibility_Review_v2.md # Gap analysis
│   └── Implementation_Roadmap_v2.md # This project plan
└── bso-survival.php               # Main plugin file

```

---

## 🚀 Fase 1: Getting Started

### What We're Doing
**Add JSON `meta_data` columns to Event, Team, Part, Assignment tables**

**Why?** Allows extensions to store custom data without database migrations.

**Example Use Case (v2.1):**
```php
$event = Event::find(1);
MetaDataHelper::set($event, 'sponsor_name', 'Acme Corp');
MetaDataHelper::set($event, 'theme_color', '#FF5733');
// These are stored in Event.meta_data as JSON
// No migration needed!
```

### Step 1: Understand MetaDataHelper

Read: [src/Support/MetaDataHelper.php](../src/Support/MetaDataHelper.php)

**Key Methods:**
- `get($entity, $key, $default)` – Retrieve value
- `set(&$entity, $key, $value)` – Store value
- `merge(&$entity, $updates)` – Store multiple values
- `delete(&$entity, $key)` – Remove value
- `all($entity)` – Get all meta data as array
- `has($entity, $key)` – Check if key exists
- `increment($entity, $key, $amount)` – Add to counter

**Why this design?**
- Type-safe validation (keys, values, JSON)
- Clear error messages
- Chainable methods (return $entity)
- Hook support for debugging

### Step 2: Run the Tests

**Setup:**
```bash
cd /config/workspace/projects/bso-survival
composer install  # Install PHPUnit if not done
```

**Run tests for Fase 1:**
```bash
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php
```

**Expected:** All 50+ tests pass ✅

**If failing?**
1. Check PHP version (7.4+ required for named parameters)
2. Verify MetaDataHelper.php is in src/Support/
3. Ensure JSON functions available (should be in PHP core)

### Step 3: Integrate into Plugin Bootstrap

**File:** `src/Core/Plugin.php` (or equivalent bootstrap)

Add to your plugin initialization:
```php
use BSO\Survival\Support\MetaDataHelper;

class Plugin {
    public static function init() {
        // ... other init code ...
        
        // Make MetaDataHelper available globally
        // (or register as a container service if using DI)
        self::boot_metadata_helper();
    }
    
    private static function boot_metadata_helper() {
        // Optionally: setup error logging hook
        add_action('bso_survival_metadata_error', function($operation, $entity, $key, $error) {
            error_log("MetaDataHelper $operation error on key '$key': " . $error->getMessage());
        }, 10, 4);
    }
}
```

### Step 4: Create Database Schema File

**File:** `database/schema.php`

```php
<?php
/**
 * BSO Survival Database Schema – v2.0
 * 
 * Defines custom table structure. Use this as source of truth.
 */

namespace BSO\Survival\Database;

class Schema {
    
    /**
     * Get all table definitions
     */
    public static function tables() {
        return [
            'events' => self::table_events(),
            'teams' => self::table_teams(),
            'parts' => self::table_parts(),
            'assignments' => self::table_assignments(),
            // ... other tables
        ];
    }
    
    /**
     * Define events table
     */
    private static function table_events() {
        return [
            'id' => 'bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT',
            'name' => 'varchar(255) NOT NULL',
            'event_date' => 'date NOT NULL',
            'status' => "enum('concept','planned','active','closed') NOT NULL DEFAULT 'concept'",
            'meta_data' => 'json DEFAULT (JSON_OBJECT())',  // NEW: Flexible data storage
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];
    }
    
    /**
     * Define teams table
     */
    private static function table_teams() {
        return [
            'id' => 'bigint(20) unsigned PRIMARY KEY AUTO_INCREMENT',
            'event_id' => 'bigint(20) unsigned NOT NULL',
            'name' => 'varchar(255) NOT NULL',
            'contact_name' => 'varchar(255)',
            'contact_phone' => 'varchar(20)',
            'contact_email' => 'varchar(255)',
            'status' => "enum('registered','active','disqualified') NOT NULL DEFAULT 'registered'",
            'meta_data' => 'json DEFAULT (JSON_OBJECT())',  // NEW: Flexible data storage
            'created_at' => 'datetime DEFAULT CURRENT_TIMESTAMP',
            'updated_at' => 'datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            'FOREIGN KEY (event_id)' => 'REFERENCES events(id) ON DELETE CASCADE',
            'INDEX' => 'event_id',
        ];
    }
    
    // ... similar for parts, assignments, etc.
    
    /**
     * Install all tables (called during plugin activation)
     */
    public static function install() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        foreach (self::tables() as $table_name => $columns) {
            $table = $wpdb->prefix . 'bso_survival_' . $table_name;
            
            // Build CREATE TABLE statement
            $sql = "CREATE TABLE IF NOT EXISTS {$table} (";
            foreach ($columns as $col_name => $col_def) {
                $sql .= "{$col_name} {$col_def},";
            }
            $sql = rtrim($sql, ',') . ") {$charset};";
            
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
        }
    }
}
```

### Step 5: Write Integration Test

**File:** `tests/Feature/MetaDataIntegrationTest.php`

```php
<?php
namespace BSO\Survival\Tests\Feature;

use BSO\Survival\Support\MetaDataHelper;
use PHPUnit\Framework\TestCase;

class MetaDataIntegrationTest extends TestCase {
    
    /**
     * Test storing custom event data
     * Simulates: Event v2.1 adds sponsorship tracking
     */
    public function test_event_sponsor_storage() {
        $event = (object) [
            'id' => 1,
            'name' => 'Summer Survival 2026',
            'meta_data' => '{}' // Simulates DB row
        ];
        
        // v2.1 extension stores sponsor info
        MetaDataHelper::merge($event, [
            'sponsor_name' => 'Acme Corporation',
            'sponsor_logo' => 'https://acme.com/logo.png',
            'sponsor_website' => 'https://acme.com',
            'sponsorship_level' => 'gold'
        ]);
        
        // v2.1 extension retrieves sponsor info
        $sponsor_name = MetaDataHelper::get($event, 'sponsor_name');
        $this->assertEquals('Acme Corporation', $sponsor_name);
        
        // Verify data structure
        $all = MetaDataHelper::all($event);
        $this->assertArrayHasKey('sponsor_name', $all);
        $this->assertCount(4, $all);
    }
    
    /**
     * Test storing complex team metadata
     * Simulates: Team has optional division/category
     */
    public function test_team_division_storage() {
        $team = (object) [
            'id' => 5,
            'name' => 'Delta Force',
            'meta_data' => '{}'
        ];
        
        // Store division & tier info (future feature)
        MetaDataHelper::set($team, 'division', [
            'id' => 'youth',
            'name' => 'Youth (12–16)',
            'tier' => 'A'
        ]);
        
        $division = MetaDataHelper::get($team, 'division');
        $this->assertEquals('youth', $division['id']);
    }
}
```

---

## 🧪 Testing Expectations

### For Fase 1

**Coverage Target:** 95%+ of MetaDataHelper methods

**Test Categories:**
1. **Happy Path** – Normal usage works correctly
2. **Edge Cases** – Empty inputs, null values, special chars
3. **Error Handling** – Invalid inputs throw proper exceptions
4. **Data Integrity** – JSON encoding/decoding preserves data
5. **Chaining** – Methods return entity for chaining

**Example Test Run:**
```bash
$ ./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php

PHPUnit 9.5.27

...................................................... [50 tests]
...................................................... [100 tests]

Time: 1.234 seconds
OK (120 tests, 0 failures)
```

### Running Specific Tests
```bash
# Run only GET tests
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php --filter test_get

# Run with verbose output
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php -v

# Generate coverage report
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php --coverage-html coverage/
```

---

## 📚 Code Standards

### Naming Conventions
- **Classes:** PascalCase (`MetaDataHelper`, `ScoringMethodInterface`)
- **Methods:** snake_case (`get_meta_value()`, `validate_entity()`)
- **Properties:** snake_case (`$event->meta_data`)
- **Constants:** UPPER_SNAKE_CASE (`JSON_DEPTH_LIMIT = 10`)

### Documentation Style
```php
/**
 * Brief description
 *
 * Longer explanation if needed.
 *
 * @param Type $parameter Description
 * @return Type Description
 * @throws Exception When...
 */
```

### Error Handling
- Use **InvalidArgumentException** for user/data errors
- Use **LogicException** for code flow errors
- Always include context in error message

Example:
```php
if (!is_string($key)) {
    throw new InvalidArgumentException(
        "Meta key must be a string, got " . gettype($key)
    );
}
```

---

## 🔗 WordPress Integration Points

### Hooks You'll Use

**When storing meta data:**
```php
do_action('bso_survival_metadata_error', $operation, $entity, $key, $error);
```

**When event status changes:**
```php
do_action('bso_survival_event_status_changed', $event_id, $old_status, $new_status);
```

**See:** [docs/hooks-and-filters.md](../docs/hooks-and-filters.md) for complete list

### WordPress Conventions
- Prefix all options: `bso_survival_*`
- Prefix all hooks: `bso_survival_*`
- Prefix all custom tables: `wp_bso_survival_*`
- Use `current_user_can()` for capabilities

---

## 💡 Common Patterns

### Pattern 1: Accessing Entity Data
```php
$event = Event::find(1); // Fetch from DB

// Get custom metadata
$sponsor = MetaDataHelper::get($event, 'sponsor_name', 'Unknown');

// Modify and save
MetaDataHelper::set($event, 'sponsor_name', 'NewCorp');
$event->save(); // Save back to DB
```

### Pattern 2: Extending Entity with Custom Fields
```php
// In v2.1 extension:
class SponsorshipExtension {
    public function register_sponsor_fields() {
        add_action('bso_survival_event_init', function($event) {
            // Initialize sponsor fields if not exist
            if (!MetaDataHelper::has($event, 'sponsor_name')) {
                MetaDataHelper::set($event, 'sponsor_name', null);
            }
        });
    }
}
```

### Pattern 3: Safe Data Retrieval
```php
// Bad: Assumes data exists
$count = MetaDataHelper::all($event)['team_count'];  // Crash if missing!

// Good: Use has() or get()
$count = MetaDataHelper::get($event, 'team_count', 0);  // Safe default

// Good: Use has() first
if (MetaDataHelper::has($event, 'team_count')) {
    $count = MetaDataHelper::get($event, 'team_count');
}
```

---

## 🐛 Debugging Tips

### Enable Debug Logging
```php
// In wp-config.php or bootstrap
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Listen for metadata errors
add_action('bso_survival_metadata_error', function($op, $entity, $key, $error) {
    error_log("Meta $op error: " . $error->getMessage());
}, 10, 4);
```

### Inspect Metadata
```php
// In WP admin or code:
$event = Event::find(1);
echo '<pre>';
print_r(MetaDataHelper::all($event));
echo '</pre>';

// Or raw DB:
SELECT meta_data FROM wp_bso_survival_events WHERE id = 1;
```

### Test Locally
```bash
# Run test suite
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php

# Test with specific method
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php --filter test_set_adds_new_key

# Output to file
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php > test_results.txt
```

---

## ✅ Fase 1 Acceptance Criteria

- [ ] MetaDataHelper class written and reviewed
- [ ] All 120+ unit tests passing
- [ ] Integration test demonstrates sponsor use case
- [ ] Database schema file created with meta_data columns
- [ ] Code follows WordPress & project standards
- [ ] Documentation updated (this file + inline comments)
- [ ] No PHP warnings or errors in tests
- [ ] Code coverage >95%

**Signoff:** When all above checked, Fase 1 is complete. Ready for Fase 2 (Scoring Methods).

---

## 📞 Getting Help

### Resources
- **FO Document:** [Functional_Design_v2.md](../docs/Functional_Design_v2.md) – Complete business requirements
- **This Roadmap:** [Implementation_Roadmap_v2.md](../docs/Implementation_Roadmap_v2.md) – Implementation steps
- **Extensibility Review:** [Extensibility_Review_v2.md](../docs/Extensibility_Review_v2.md) – Architecture deep-dive

### Common Questions

**Q: Why JSON for meta_data instead of separate columns?**  
A: JSON is flexible—new extensions don't need migrations. Only downside: querying meta_data is slower, but for BSO Survival scale (22 teams max), not an issue.

**Q: Can I query meta_data in SQL?**  
A: Yes! Modern MySQL (5.7+) supports JSON path queries:
```sql
SELECT * FROM wp_bso_survival_events 
WHERE JSON_EXTRACT(meta_data, '$.sponsor_name') = 'Acme Corp';
```

**Q: Why use InvalidArgumentException?**  
A: It's the WordPress standard for data validation errors. LogicException is for programmer errors.

**Q: Do I need to update the FO?**  
A: Yes. After Fase 1 completes, update [Functional_Design_v2.md](../docs/Functional_Design_v2.md) section 5 (Datamodel) with meta_data fields documented.

---

## 🎯 Next Phase Preview

After Fase 1 is done and merged:

**Fase 2: Scoring Method Registry** (Week 2–3)
- Create `ScoringMethodInterface`
- Build registry for time/points/distance scorers
- Allow plugins to register custom scorers
- Update admin UI dropdown

---

*Document maintained by: Product Team  
Last updated: 7 July 2026  
Questions? Slack #bso-survival-dev or create GitHub issue*
