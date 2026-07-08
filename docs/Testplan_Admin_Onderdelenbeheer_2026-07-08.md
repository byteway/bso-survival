# Testplan Admin onderdelenbeheer

Datum: 8 juli 2026
Scope: US-B1d, US-C0, US-C4

## Doel

Valideren dat beheerders onderdelen veilig kunnen beheren, importeren/exporteren en alleen geldige onderdelen aan events kunnen koppelen.

## Voorwaarden

- Beheerder is ingelogd met `manage_options`.
- Plugin `bso-survival` is actief.
- Er bestaat minimaal 1 concept/actief event en 1 afgesloten event.
- Er zijn testonderdelen beschikbaar, inclusief ten minste 1 onderdeel gekoppeld aan een actief ander event.

## Testgevallen

### 1. Onderdeel aanmaken

1. Open `Survival -> Onderdelen`.
2. Vul naam `Kanovaren UAT`, status `Actief` en optioneel coordinaten in.
3. Sla op.
4. Verwacht: succesmelding `Onderdeel aangemaakt` en onderdeel verschijnt in de lijst.

### 2. Onderdeel bewerken

1. Klik `Bewerken` bij `Kanovaren UAT`.
2. Wijzig naam naar `Kanovaren UAT XL` en status naar `Inactief`.
3. Sla op.
4. Verwacht: succesmelding `Onderdeel bijgewerkt` en lijst toont gewijzigde waarden.

### 3. Veilig verwijderen blokkeren

1. Koppel een onderdeel aan een actief event.
2. Open `Survival -> Onderdelen` en probeer dit onderdeel te verwijderen.
3. Verwacht: foutmelding dat verwijderen niet kan zolang het onderdeel aan een actief event gekoppeld is.
4. Verwacht: onderdeel blijft aanwezig en gekoppeld.

### 4. Veilig verwijderen toestaan

1. Gebruik een onderdeel dat niet gekoppeld is of alleen aan een afgesloten event hing.
2. Verwijder dit onderdeel.
3. Verwacht: succesmelding en onderdeel verdwijnt uit de actieve onderdelenlijst.

### 5. Export onderdelen

1. Open `Survival -> Onderdelen`.
2. Klik `Exporteer onderdelen als JSON`.
3. Verwacht: download van JSON-bestand met leesbare array van onderdelen.
4. Controleer dat elk record minimaal `name` en `status` bevat.

### 6. Import geldige JSON

1. Gebruik een JSON-bestand met minimaal 2 unieke onderdelen.
2. Importeer via bestand of geplakte JSON.
3. Verwacht: succesmelding met aantal geimporteerde onderdelen.
4. Verwacht: nieuwe onderdelen zijn direct zichtbaar in de onderdelenlijst.

### 7. Import weigert duplicaten of ongeldige JSON

1. Importeer JSON met dubbele `name` of syntactisch ongeldige JSON.
2. Verwacht: duidelijke foutmelding.
3. Verwacht: geen gedeeltelijke import van nieuwe onderdelen.

### 8. Event-bewerking toont alleen geldige koppelbare onderdelen

1. Open `Survival -> Events` en kies een conceptevent.
2. Controleer onderdelenlijst.
3. Verwacht: onderdelen gekoppeld aan actief ander event ontbreken.
4. Verwacht: onderdelen gekoppeld aan afgesloten/gepubliceerd event mogen wel opnieuw worden gekozen.
5. Verwacht: onderdelen met conflicterende naam binnen hetzelfde event worden niet als extra keuze getoond.

### 9. Onderdelenfilter op eventpagina

1. Open `Survival -> Events` bij een bestaand event.
2. Vul deel van een onderdeelnaam in het filterveld in.
3. Klik `Filter`.
4. Verwacht: alleen overeenkomende, geldig koppelbare onderdelen worden getoond.
5. Sla koppelingen op terwijl filter actief is.
6. Verwacht: al gekoppelde maar tijdelijk verborgen onderdelen blijven gekoppeld.

### 10. Event basisgegevens bewerken

1. Open `Survival -> Events` en kies een conceptevent.
2. Wijzig naam, datum of `max teams`.
3. Sla op.
4. Verwacht: succesmelding `Event bijgewerkt`.
5. Verwacht: gewijzigde gegevens blijven zichtbaar na reload.

## Automatische regressie

```bash
./scripts/run-admin-event-tests.sh
./scripts/run-admin-part-tests.sh
vendor/bin/phpunit
```

## Verwachte status

- Gericht: alle admin event/part tests groen.
- Regressie: volledige PHPUnit-suite groen.
- Handmatig: alle 10 testgevallen akkoord of gedocumenteerde bevindingen met bewijs.
