# BSO Survival v2

Minimale, schone basis voor de volgende ontwikkelfase van de BSO Survival plugin.

## Doel
Deze setup levert een duidelijke scheiding tussen:
- actieve v2-code in src, tests, templates, assets
- gearchiveerde v1-code in legacy

## Huidige onderdelen
- Plugin bootstrap: bso-survival.php
- Shortcode: [bso_survival_dashboard]
- Basale frontend assets: assets/css/bso-survival.css, assets/js/bso-survival.js
- Database schema-startpunt: database/schema.php + src/Database/Schema.php
- Testbasis: tests/Support/MetaDataHelperTest.php

## Ontwikkelcommando's
- composer install
- ./vendor/bin/phpunit tests/Support/MetaDataHelperTest.php
