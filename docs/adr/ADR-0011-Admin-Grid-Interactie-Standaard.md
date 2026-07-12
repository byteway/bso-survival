# ADR-0011: Admin Grid Interactie Standaard

## Status
Accepted - 2026-07-12

## Context
Meerdere adminpagina's in de plugin bevatten interactieve grids met sorteren, rijselectie en een edit-flow naar een zijpaneel (flip-over). In het verleden ontstonden stijl- en interactieverschillen tussen pagina's.

Doel van deze ADR:
- uniforme UX voor alle interactieve admingrids
- herhaalbare implementatie voor nieuwe pagina's
- regressiepreventie via geautomatiseerde checks

## Besluit
Voor elke nieuwe of aangepaste interactieve admingrid gelden deze verplichte conventies.

### 1) Rijselectie en visuele status
- Geselecteerde rij heeft duidelijke linkeraccentborder (blauw) en subtiele achtergrond.
- Hover en focus tonen visuele feedback.
- Eerste cel bevat een interactie-indicator-icoon bij hover/focus/selected.

### 2) Sorteerheaders
- Sorteerpijlen zijn altijd zichtbaar.
- Niet-actieve kolommen tonen lichtgrijze `↕`.
- Actieve kolom toont donkere `▲` of `▼`.

### 3) Toegankelijkheid
- Klikbare rijen zijn focusbaar (`tabindex="0"`) en hebben `role="button"`.
- Toetsen `Enter` en `Spatie` openen dezelfde edit-actie als muisklik.

### 4) Interactiegrenzen
- Een actiekolom met eigen knoppen/links triggert geen rij-edit.
- Inline acties (zoals Delete) moeten een bevestigingsvraag tonen.

### 5) Edit-flow
- Nieuwe records en bewerken van bestaande records gebruiken hetzelfde editpaneel (flip-over) waar functioneel passend.

## Implementatierichtlijn
Gebruik deze bouwblokken in adminpagina's:
- rijclass `*-row-clickable`
- sorteerlinkclass `*-sort-link` + `*-sort-arrow`
- geselecteerde rijclass `is-selected`
- keydown-listener met `Enter`/`Space`

## Borging
Automatische borging is toegevoegd via testbestand:
- `tests/Admin/AdminGridConventionTest.php`

Deze test valideert op de kernschermen dat interactieve gridconventies niet ongemerkt verwijderd worden.

Bij nieuwe interactieve admingrids:
1. Pas deze ADR toe.
2. Breid de conventietest uit met het nieuwe bestand.

## Gevolgen
- Consistente admin-UX over schermen heen.
- Lagere kans op regressies bij refactors.
- Duidelijke ontwikkelstandaard voor toekomstige grids.
