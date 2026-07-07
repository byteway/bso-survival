# BSO Survival v2

BSO Survival v2 is de schone, uitbreidbare basis voor de volgende ontwikkelfase van de plugin.

## Status

De codebase staat nu in een vroeg maar werkend v2-fundament:
- plugin bootstrap en activatie zijn aanwezig
- het datamodel is volledig gedefinieerd
- migratie draait bij activatie
- golden dataset en unit tests zijn beschikbaar
- repositories en services vormen de huidige leeslaag

De dagafsluiting wordt hier bewust nog niet functioneel uitgewerkt. Die moet later aansluiten op de bestaande services en repositories, zodat de eindstand, certificaten en read-only afsluiting vanuit een stabiele basis worden opgebouwd.

## Huidige onderdelen

- Plugin bootstrap: [bso-survival.php](bso-survival.php)
- Activatie en migratie: [src/Core/Activator.php](src/Core/Activator.php) en [src/Database/Migrator.php](src/Database/Migrator.php)
- Datamodel: [src/Database/Schema.php](src/Database/Schema.php)
- Repositories: [src/Database/Repository](src/Database/Repository)
- Services: [src/Service](src/Service)
- Read-only dashboard basis: [src/Frontend/DashboardController.php](src/Frontend/DashboardController.php) en [templates/frontend-dashboard.php](templates/frontend-dashboard.php)
- Golden dataset: [tests/Support/GoldenDataset.php](tests/Support/GoldenDataset.php)
- Regressietests: [tests/Support](tests/Support) en [tests/Service](tests/Service)
- Dagafsluitingsvoorbereiding: [docs/Dagafsluiting_Voorbereiding.md](docs/Dagafsluiting_Voorbereiding.md)

## Wat nog niet is uitgewerkt

- dagafsluiting-workflow
- certificaatgeneratie voor eindverwerking
- podium- en eindstandlogica als eindproces
- publicatie- of afsluitstatus na de survivaldag
- dagafsluiting wordt nu alleen voorbereid, niet definitief gemaakt

## Ontwikkelcommando's

- composer install
- ./vendor/bin/phpunit tests/Support/GoldenDatasetTest.php
- ./vendor/bin/phpunit tests/Service/RepositoryTest.php tests/Service/ServiceLayerTest.php
