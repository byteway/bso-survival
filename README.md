# BSO Survival v2

BSO Survival v2 is de schone, uitbreidbare basis voor de volgende ontwikkelfase van de plugin.

Laatste documentatie-update: 7 juli 2026.

## Status

De codebase staat nu in een vroeg maar werkend v2-fundament:
- plugin bootstrap en activatie zijn aanwezig
- het datamodel is volledig gedefinieerd
- migratie draait bij activatie
- golden dataset en unit tests zijn beschikbaar
- repositories en services vormen de huidige leeslaag
- read-only frontend shortcodes zijn beschikbaar (dashboard, onderdelen, teams, gecombineerde varianten)
- scoremethode-architectuur voor Fase 2 is opgezet (interface, registry, defaults)
- dashboard widget-architectuur voor Fase 3 is gestart (interface, registry, sectioning, default widgets)

De dagafsluiting wordt hier bewust nog niet functioneel uitgewerkt. Die moet later aansluiten op de bestaande services en repositories, zodat de eindstand, certificaten en read-only afsluiting vanuit een stabiele basis worden opgebouwd.

## Huidige onderdelen

- Plugin bootstrap: [bso-survival.php](bso-survival.php)
- Activatie en migratie: [src/Core/Activator.php](src/Core/Activator.php) en [src/Database/Migrator.php](src/Database/Migrator.php)
- Datamodel: [src/Database/Schema.php](src/Database/Schema.php)
- Repositories: [src/Database/Repository](src/Database/Repository)
- Services: [src/Service](src/Service)
- Scoring contracts: [src/Contracts/ScoringMethodInterface.php](src/Contracts/ScoringMethodInterface.php)
- Scoring registry: [src/Service/ScoringMethodRegistry.php](src/Service/ScoringMethodRegistry.php)
- Scoring methods: [src/Service/ScoringMethods](src/Service/ScoringMethods)
- Dashboard widget contract: [src/Contracts/DashboardWidgetInterface.php](src/Contracts/DashboardWidgetInterface.php)
- Dashboard widget registry: [src/Service/DashboardWidgetRegistry.php](src/Service/DashboardWidgetRegistry.php)
- Dashboard widgets: [src/Widgets](src/Widgets)
- Dashboard widget assets: [assets/css/bso-survival-dashboard-widgets.css](assets/css/bso-survival-dashboard-widgets.css) en [assets/js/bso-survival-dashboard-widgets.js](assets/js/bso-survival-dashboard-widgets.js)
- PartRule configuratie-service: [src/Service/PartRuleConfiguratorService.php](src/Service/PartRuleConfiguratorService.php)
- Scoreberekening op PartRule: [src/Service/ScoreComputationService.php](src/Service/ScoreComputationService.php)
- Admin configuratiepagina: [src/Admin/PartRuleAdminPage.php](src/Admin/PartRuleAdminPage.php)
- Frontend controllers: [src/Frontend](src/Frontend)
- Frontend templates: [templates](templates)
- Golden dataset: [tests/Support/GoldenDataset.php](tests/Support/GoldenDataset.php)
- Regressietests: [tests/Support](tests/Support) en [tests/Service](tests/Service)
- Dagafsluitingsvoorbereiding: [docs/Dagafsluiting_Voorbereiding.md](docs/Dagafsluiting_Voorbereiding.md)
- Hooks en shortcodes: [docs/hooks-and-filters.md](docs/hooks-and-filters.md)

## Frontend shortcodes (actueel)

- `[bso_survival_dashboard]`
	- Attributen: `event_id`, `title`
- `[bso_survival_parts]`
	- Attributen: `event_id`, `title`
- `[bso_survival_teams]`
	- Attributen: `event_id`, `title`
- `[bso_survival_event_overview]`
	- Attributen: `event_id`, `title`, `compact` (`yes`/`no`, default `no`)
- `[bso_survival_event_summary]`
	- Attributen: `event_id`, `title`

Voorbeeld:

```text
[bso_survival_event_overview title="Gecombineerd Overzicht Event 2" event_id="2" compact="yes"]
```

## Wat nog niet is uitgewerkt

- dagafsluiting-workflow
- certificaatgeneratie voor eindverwerking
- podium- en eindstandlogica als eindproces
- publicatie- of afsluitstatus na de survivaldag
- dagafsluiting wordt nu alleen voorbereid, niet definitief gemaakt

## Ontwikkelcommando's

- composer install
- ./vendor/bin/phpunit
- ./vendor/bin/phpunit tests/Service/EventOverviewControllerTest.php
- ./vendor/bin/phpunit tests/Service/EventSummaryControllerTest.php
- ./vendor/bin/phpunit tests/Service/ScoringMethodRegistryTest.php
- ./vendor/bin/phpunit tests/Service/ScoringMethodsTest.php
- ./vendor/bin/phpunit tests/Service/PartRuleConfiguratorServiceTest.php
- ./vendor/bin/phpunit tests/Service/PartRuleScoringFlowTest.php

Huidige teststatus: 72/72 groen.
