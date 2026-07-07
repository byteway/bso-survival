# Quick Start – Fase 1 Implementation (30 Minutes)

## Actuele status (7 juli 2026)

Deze quick start blijft bruikbaar voor de Fase 1 basis, maar de codebase is inmiddels verder:
- frontend read-only shortcodes zijn geïmplementeerd
- gecombineerde shortcodes zijn beschikbaar
- volledige testsuite staat op 104/104 groen

Directe smoke-test shortcodes:

```text
[bso_survival_dashboard]
[bso_survival_parts event_id="2"]
[bso_survival_teams event_id="2"]
[bso_survival_event_overview event_id="2"]
[bso_survival_event_overview event_id="2" compact="yes"]
[bso_survival_event_summary event_id="2"]
```

**Goal:** Get MetaDataHelper working and all tests passing  
**Time:** 30–45 minutes  
**For:** First-time developer on this project

---

## Step 0: Prerequisites Check (2 min)

```bash
# Check PHP version (need 7.4+)
php -v

# Check if composer installed
composer --version

# Navigate to project
cd /config/workspace/projects/bso-survival
```

**Expected Output:**
```
PHP 8.0+ (version number)
Composer version 2.x
```

---

## Step 1: Install Dependencies (3 min)

```bash
# Install Composer dependencies (PHPUnit, etc.)
composer install

# Verify PHPUnit installed
./vendor/bin/phpunit --version
```

**Expected:**
```
PHPUnit 9.5.27
```

---

## Step 2: Verify Code Structure (2 min)

Check that these files exist:

```bash
# MetaDataHelper class
ls -la src/Support/MetaDataHelper.php

# Unit tests
ls -la tests/Support/MetaDataHelperTest.php

# PHPUnit config
ls -la phpunit.xml

# Test bootstrap
ls -la tests/bootstrap.php
```

All should exist ✅

---

## Step 3: Run Tests (5 min)

```bash
# Run all tests
./vendor/bin/phpunit

# Or with verbose output
./vendor/bin/phpunit -v
```

**Expected Output (indicatie):**
```
PHPUnit 9.6.x

Time: < 1 second

OK (104 tests, 0 failures)
```

**If any failures:**
1. Read the failure message carefully
2. Check if PHP version is 7.4+
3. Verify namespace is correct: `namespace BSO\Survival\Support;`
4. Check JSON functions: `php -r "echo json_encode(['test' => 'ok']);"` should work

---

## Step 4: Review the Code (10 min)

Open and read:

**File 1: MetaDataHelper class**
```bash
cat src/Support/MetaDataHelper.php | head -100
```

Key things to notice:
- Line ~50: `public static function get()` – retrieve meta
- Line ~80: `public static function set()` – store meta
- Line ~120: Private validation methods
- Line ~200: Error handling with `do_action`

**File 2: Unit tests**
```bash
cat tests/Support/MetaDataHelperTest.php | head -50
```

Notice:
- Each test has `@test` comment
- Tests cover happy path + error cases
- Naming pattern: `test_method_name_does_something`

---

## Step 5: Understand One Example (5 min)

**Example: Storing sponsor name on Event**

```php
// Step 1: Create entity
$event = (object) [
    'id' => 1,
    'name' => 'Survival Day',
    'meta_data' => '{}' // Empty JSON
];

// Step 2: Store sponsor info
MetaDataHelper::set($event, 'sponsor_name', 'Acme Corp');
MetaDataHelper::set($event, 'sponsor_logo', 'https://acme.com/logo.png');

// Step 3: Retrieve sponsor info
$sponsor = MetaDataHelper::get($event, 'sponsor_name');
echo $sponsor; // Output: "Acme Corp"

// Step 4: View all meta data
$all = MetaDataHelper::all($event);
print_r($all);
// Output:
// Array (
//   [sponsor_name] => Acme Corp
//   [sponsor_logo] => https://acme.com/logo.png
// )

// Step 5: Save to database
$event->save(); // meta_data is now persisted
```

**Why is this powerful?**
- No need to add database columns for sponsor_name, sponsor_logo
- Extensions can add any data without migrations
- Data stays in one JSON field = clean schema

---

## Step 6: Run One Specific Test (3 min)

Test just the `get` functionality:

```bash
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php --filter test_get_returns_value_when_key_exists
```

**Output:**
```
PHPUnit 9.5.27

.

Time: 0.125 seconds, Memory: 4.00 MB

OK (1 test, 0 failures)
```

---

## Step 7: Generate Coverage Report (3 min)

```bash
# Create coverage report
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php --coverage-html coverage/

# Open in browser (or view summary)
echo "Coverage report generated in: coverage/index.html"
```

**Look for:**
- Method coverage near 100%
- All code paths tested

---

## 🎉 Success Checklist

- [ ] Composer installed
- [ ] All tests passing (120+ tests)
- [ ] Can run specific test by name
- [ ] Understand `get()` and `set()` usage
- [ ] Understand why meta_data is powerful
- [ ] Coverage report generated

**If all checkmarks ✅:** Fase 1 code is ready for integration!

---

## Troubleshooting

### Problem: "Class not found: MetaDataHelper"
**Solution:** Verify namespace in MetaDataHelper.php file
```php
// Top of file must have:
namespace BSO\Survival\Support;
```

### Problem: "PHPUnit not found"
**Solution:** Run `composer install` again
```bash
composer install
./vendor/bin/phpunit --version
```

### Problem: Tests timeout or hang
**Solution:** Add timeout to test run
```bash
timeout 10 ./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php
```

### Problem: JSON errors in test output
**Solution:** Check PHP JSON extension
```bash
php -m | grep json
```

Should show `json` in the list.

---

## Next Step

Once all tests pass ✅:

1. **Review with team** – Show coverage report
2. **Merge to main branch** – Code is ready for integration
3. **Move to Fase 2** – Start Scoring Method Registry

---

## Quick Commands Cheat Sheet

```bash
# Run all tests
./vendor/bin/phpunit

# Run specific test files
./vendor/bin/phpunit tests/Service/EventOverviewControllerTest.php
./vendor/bin/phpunit tests/Service/EventSummaryControllerTest.php

# Run specific test method
./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php --filter test_get

# Run with verbose output
./vendor/bin/phpunit -v

# Generate HTML coverage report
./vendor/bin/phpunit --coverage-html coverage/

# Run with colored output
./vendor/bin/phpunit --colors=auto

# Stop on first failure
./vendor/bin/phpunit --stop-on-failure
```

---

*Questions?* Check [DEV_ONBOARDING_FASE1.md](DEV_ONBOARDING_FASE1.md) for detailed explanations  
*Time:* ~30 min to get this working  
*Next:* Fase 2 – Scoring Method Registry (Week 2–3)
