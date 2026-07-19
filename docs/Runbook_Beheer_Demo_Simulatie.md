# Runbook Beheer - Demo Data Simulatie Test

Laatste update: 10 juli 2026.

## Doel

Met dit runbook kan beheer snel demo-scores plaatsen op bestaande score-records, zodat het verloop van de dag op meerdere momenten reproduceerbaar getest kan worden.

Gebruik dit runbook voor:

- functionele simulatie van tussentijdse standen;
- regressietest van frontend score-overzichten;
- regressietest van bonus/tie-resolutie op onderdeelscore;
- acceptatietest op eventmonitoring tijdens de dag.

## Scope

Deze procedure gebruikt de command:

- wp bso-survival seed-demo-scores

Gedrag:

- update-only op bestaande score-records;
- idempotent (herhaald uitvoeren maakt geen extra score-entries);
- met --slot op specifieke tijdsloten;
- zonder --slot op alle tijdsloten van het event.

## Voorwaarden

1. Event bestaat en heeft planning (timeslots + assignments).
2. Score-records bestaan al per assignment (via Initialiseer scores of demo-opbouw bij eventcreatie).
3. WP-CLI is beschikbaar.
4. De command wordt uitgevoerd met juiste WordPress pad en DB environment vars.

Gebruik in gedeelde documentatie nooit echte wachtwoorden. Vervang `<DB_PASSWORD>` altijd lokaal met een eigen secret of laad deze uit een niet-geversioneerd env-bestand.

## Stap 1 - Event en tijdsloten controleren

Controleer of het event tijdsloten heeft:

```bash
mysql -h wordpress-db -u wordpress -p'<DB_PASSWORD>' wordpress -e "
SELECT id, name FROM wp_bso_survival_events ORDER BY id;
SELECT id, event_id, start_at, end_at
FROM wp_bso_survival_timeslots
WHERE event_id = 7
ORDER BY start_at;" 2>/dev/null
```

## Stap 2 - Bestaande score-records controleren

Controleer of er score-records bestaan voor het event:

```bash
mysql -h wordpress-db -u wordpress -p'<DB_PASSWORD>' wordpress -e "
SELECT COUNT(*) AS score_records
FROM wp_bso_survival_score_entries se
JOIN wp_bso_survival_assignments a ON a.id = se.assignment_id
JOIN wp_bso_survival_timeslots ts ON ts.id = a.timeslot_id
WHERE ts.event_id = 7;" 2>/dev/null
```

Als dit 0 oplevert, maak eerst score-records aan via Admin - Survival - Score Invoer - Initialiseer scores.

## Stap 3 - Demo-seeding uitvoeren

### Variant A - Alle tijdsloten van het event

```bash
cd /config/workspace/projects/bso-survival
WORDPRESS_DB_HOST=wordpress-db \
WORDPRESS_DB_NAME=wordpress \
WORDPRESS_DB_USER=wordpress \
WORDPRESS_DB_PASSWORD='<DB_PASSWORD>' \
wp --path=/var/www/html --allow-root bso-survival seed-demo-scores --event-id=7
```

### Variant B - Specifieke tijdsloten (bijvoorbeeld 1, 6, 9, 12)

```bash
cd /config/workspace/projects/bso-survival
WORDPRESS_DB_HOST=wordpress-db \
WORDPRESS_DB_NAME=wordpress \
WORDPRESS_DB_USER=wordpress \
WORDPRESS_DB_PASSWORD='<DB_PASSWORD>' \
wp --path=/var/www/html --allow-root bso-survival seed-demo-scores --slot=1,6,9,12 --event-id=7
```

Belangrijk:

- Zet geen spaties in --slot waarden (goed: 1,6,9,12; fout: 1,6, 9,12).

## Stap 4 - Verificatie na seeding

### 4.1 Aantal score-records onveranderd (idempotent check)

```bash
before=$(mysql -h wordpress-db -u wordpress -p'<DB_PASSWORD>' wordpress -N -e "SELECT COUNT(*) FROM wp_bso_survival_score_entries;")
cd /config/workspace/projects/bso-survival
WORDPRESS_DB_HOST=wordpress-db WORDPRESS_DB_NAME=wordpress WORDPRESS_DB_USER=wordpress WORDPRESS_DB_PASSWORD='<DB_PASSWORD>' \
wp --path=/var/www/html --allow-root bso-survival seed-demo-scores --slot=1,2,3,4,5,6 --event-id=7 >/tmp/seed_demo_out.txt
after=$(mysql -h wordpress-db -u wordpress -p'<DB_PASSWORD>' wordpress -N -e "SELECT COUNT(*) FROM wp_bso_survival_score_entries;")
echo "before=$before after=$after"
cat /tmp/seed_demo_out.txt
```

Verwachting:

- before en after gelijk;
- output toont updated > 0 en inserted = 0.

### 4.2 Controle per tijdslot

```bash
mysql -h wordpress-db -u wordpress -p'<DB_PASSWORD>' wordpress -e "
SELECT
  ROW_NUMBER() OVER (ORDER BY ts.start_at) AS slot_nr,
  ts.id AS timeslot_id,
  COUNT(se.id) AS score_count
FROM wp_bso_survival_timeslots ts
JOIN wp_bso_survival_assignments a ON a.timeslot_id = ts.id
JOIN wp_bso_survival_score_entries se ON se.assignment_id = a.id
WHERE ts.event_id = 7
GROUP BY ts.id, ts.start_at
ORDER BY ts.start_at;" 2>/dev/null
```

### 4.3 Bonusvelden controleren

```bash
mysql -h wordpress-db -u wordpress -p'<DB_PASSWORD>' wordpress -e "
SELECT se.id, a.team_id, a.part_id, se.raw_value, se.bonus_points, se.joker_applied
FROM wp_bso_survival_score_entries se
JOIN wp_bso_survival_assignments a ON a.id = se.assignment_id
JOIN wp_bso_survival_timeslots ts ON ts.id = a.timeslot_id
WHERE ts.event_id = 7
ORDER BY ts.start_at, a.part_id, a.team_id
LIMIT 40;" 2>/dev/null
```

Verwachting:

- `bonus_points` bestaat en is numeriek (standaard `0.00` als geen bonus is gezet);
- bij gelijke ruwe score kan bonus in admin worden verhoogd om tie-volgorde zichtbaar te veranderen in de shortcode-tabellen.

## Veelvoorkomende fouten en oplossing

### Fout 1 - No WordPress installation found

Oorzaak:

- command uitgevoerd in pluginmap zonder --path.

Oplossing:

- altijd --path=/var/www/html toevoegen.

### Fout 2 - bso-survival is not a registered wp command

Oorzaak:

- WP-CLI draait buiten de juiste WordPress context.

Oplossing:

- gebruik --path=/var/www/html en de benodigde DB env vars.

### Fout 3 - php_network_getaddresses for mysql failed

Oorzaak:

- wp-config leest standaard DB host mysql, maar in deze omgeving is host wordpress-db.

Oplossing:

- zet WORDPRESS_DB_HOST=wordpress-db mee in dezelfde command.

## Operationeel advies

1. Test eerst met een beperkt slotbereik (bijv. --slot=1,6).
2. Verifieer frontend team- en onderdeelscorepagina na elke run.
3. Gebruik zonder --slot alleen als je het volledige dagbeeld in een keer wilt simuleren.
4. Draai de idempotent check periodiek in regressietests.

## Gerelateerde documentatie

- [README.md](../README.md)
- [docs/Admin_Eventbeheer.md](Admin_Eventbeheer.md)
- [docs/Functional_Design_v2.md](Functional_Design_v2.md)
