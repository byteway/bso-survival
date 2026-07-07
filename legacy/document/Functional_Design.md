# Functioneel Ontwerp - BSO Survival

## Inhoudsopgave

1. [Inleiding en doel](#1-inleiding-en-doel)
2. [Architectuuroverzicht](#2-architectuuroverzicht)
3. [Datamodel](#3-datamodel)
4. [Beheer - Adminomgeving](#4-beheer---adminomgeving)
5. [Frontend - Shortcode Kaart](#5-frontend---shortcode-kaart)
6. [REST API](#6-rest-api)
7. [Bedrijfslogica](#7-bedrijfslogica)
8. [Klassenstructuur](#8-klassenstructuur)
9. [Activatie en Deinstallatie](#9-activatie-en-deinstallatie)
10. [Assets en Scripts](#10-assets-en-scripts)
11. [Gebruikersrollen en toegang](#11-gebruikersrollen-en-toegang)

---

## 1. Inleiding en doel

BSO Survival digitaliseert het scoreproces van het jaarlijkse survival-evenement in Zwolle (Westenholte). De plugin vervangt handmatige Excel-afhandeling door centrale online registratie en publicatie van tussenstanden.

### Doelgroepen

| Doelgroep | Context | Primaire behoefte |
|---|---|---|
| Organisatoren | WordPress beheer | Instellingen beheren, scoreberekening starten, overzichten tonen |
| Scheidsrechters | Ingelogde rol referee/administrator | Scores en posities invoeren per survival en team |
| Teamleiders/teams/publiek | Frontend | Tussenstanden, survivalinformatie en teamresultaten bekijken |

### Kernfunctionaliteit

| Onderdeel | Omschrijving |
|---|---|
| Datalaag | Tabellen voor team, survival, referee, timeslot en score |
| Scoring | Positiegebaseerde puntenberekening met joker-verdubbeling |
| Admin | Instellingenpagina en toolspagina voor berekening/controle |
| Frontend | Shortcodes voor scorelijsten, updateformulier en survival-help |
| Lokalisatie | Nederlands en Engels voor teksten en help-partials |

---

## 2. Architectuuroverzicht

```mermaid
graph TD
		A[WordPress Plugin Bootstrap survival.php] --> B[Core Class Survival]
		B --> C[Survival_Loader]
		C --> D[Admin Hooks]
		C --> E[Public Hooks]
		C --> F[i18n Hooks]

		D --> G[Survival_Admin]
		E --> H[Survival_Public]
		F --> I[Survival_i18n]

		G --> J[(wp_team)]
		G --> K[(wp_survival)]
		G --> L[(wp_referee)]
		G --> M[(wp_timeslot)]
		G --> N[(wp_score)]

		H --> O[Shortcodes op pagina's]
		O --> P[Publieke tabellen en help-content]
```

Architectuurkenmerken:

- De plugin gebruikt een loader-patroon om WordPress hooks centraal te registreren.
- De adminlaag bevat zowel beheerpagina's als meerdere business-shortcodes.
- De publieke laag levert vooral meertalige helpweergave en pagina-partials.

---

## 3. Datamodel

### ER-model

```mermaid
erDiagram
		TEAM ||--o{ SCORE : heeft
		SURVIVAL ||--o{ SCORE : bevat
		TIMESLOT ||--o{ SCORE : plant
		SURVIVAL ||--o{ REFEREE : toegewezen

		TEAM {
			int id PK
			string name
			string notes
		}

		SURVIVAL {
			int id PK
			string name
			string notes
		}

		REFEREE {
			int id PK
			string name
			string email
			int survival_id FK
			string notes
		}

		TIMESLOT {
			int id PK
			int start_time_hour
			int start_time_minute
			string starttime_generated
		}

		SCORE {
			int id PK
			int team_id FK
			int survival_id FK
			int timeslot_id FK
			datetime starttime
			bool joker
			int time_min_score
			int time_sec_score
			int points_score
			int error_score
			int position
			int total
			string notes
			string time_score_generated
		}
```

### Tabeltoelichtingen per veld

| Tabel | Belangrijkste velden | Functionele betekenis |
|---|---|---|
| team | id, name, notes | Teamidentiteit en notities |
| survival | id, name, notes | Survivalonderdelen/wedstrijden |
| referee | id, name, email, survival_id | Scheidsrechter gekoppeld aan survival |
| timeslot | id, start_time_hour, start_time_minute, starttime | Speelvensters op de dag |
| score | team_id, survival_id, timeslot_id, joker, position, total | Registratie en berekening van scores |

### Initiele dataset (activatie)

| Entity | Initieel |
|---|---|
| Teams | 20 |
| Survivals | 12 |
| Referees | 20 |
| Timeslots | 12 |
| Score-rijen | 2880 (vooraf gegenereerd rooster) |

---

## 4. Beheer - Adminomgeving

### Menuboom

```mermaid
flowchart TD
		A[WordPress Admin] --> B[Settings]
		A --> C[Tools]
		B --> D[Survival Settings]
		C --> E[Survival Tools]

		D --> D1[General Settings]
		D --> D2[Shortcodes]
		D --> D3[Survival Help]

		E --> E1[General - Calculate Score]
		E --> E2[Update - Survival Position]
		E --> E3[Team Position]
```

### Submenu: Survival Settings - General (Update instelling)

```mermaid
flowchart TD
		A[Open Settings tab General] --> B[Vul URL-velden in]
		B --> C[Opslaan via options.php]
		C --> D[WordPress update_option]
		D --> E[Shortcodes gebruiken URL's]
```

Formuliervelden:

| Veld | Optie | Gebruik |
|---|---|---|
| Team score page URL | survival_team_score_page | Link voor teamscore-overzichten |
| Survival page URL | survival_survival_page | Link voor survival-overzichten |
| Score update page URL | survival_score_page | Link naar updateformulier |

### Submenu: Survival Tools - General (Create/Herbereken score)

```mermaid
flowchart TD
		A[Klik Calculate Score] --> B[admin_post_calculateScore]
		B --> C[Reset total in score tabel]
		C --> D[Loop survivals en posities]
		D --> E[Pas jokerlogica toe]
		E --> F[Sla total per score-rij op]
		F --> G[Toon bevestiging]
```

### Submenu: Survival Tools - Update (Read)

```mermaid
flowchart TD
		A[Open tab Update] --> B[Selecteer Survival]
		B --> C[Lees score-rijen op positie]
		C --> D[Toon tabel met positie/tijd/fout/punten]
```

### Submenu: Survival Tools - Team Position (Read)

```mermaid
flowchart TD
		A[Open tab Team Position] --> B[Aggregatie sum(total) per team]
		B --> C[Sorteer aflopend]
		C --> D[Toon rankinglijst]
```

---

## 5. Frontend - Shortcode Kaart

### Shortcode definitie

| Shortcode | Type | Functie |
|---|---|---|
| [survival_list] | Data | Lijst van survivals |
| [team_list] | Data | Lijst teams met link naar teamscores |
| [referee_list] | Data | Lijst scheidsrechters |
| [referee_survival_list] | Data | Scheidsrechter met survival-link |
| [team_score_survival_list] | Data | Team -> alle survival-scores |
| [survival_team_score] | Data | Survival -> teams per timeslot |
| [team_score_update] | Form | Score invoeren/bijwerken |
| [display_score] | Data | Eindranking |
| [survival_update_position] | Data | Positieoverzicht per survival |
| [survival_help], [survival_page], [survival_01_page] ... [survival_11_page] | Help | Meertalige hulpinhoud |

### Renderflow

```mermaid
flowchart TD
		A[WordPress pagina met shortcode] --> B[Shortcode callback]
		B --> C{Data nodig?}
		C -- Ja --> D[Query op plugin tabellen]
		C -- Nee --> E[Load partial]
		D --> F[HTML tabel/form render]
		E --> F
		F --> G[Output naar frontend]
```

### Componentdiagram frontend

```mermaid
graph TD
		A[Pagina met shortcodes] --> B[Scorelijsten]
		A --> C[Survival help tabs]
		A --> D[Updateformulier score]
		B --> E[Teamfilter dropdown]
		B --> F[Survivalfilter dropdown]
		D --> G[Velden: tijd, joker, punten, fouten, positie]
```

### Popup-opbouw

Geen modal popup-component aangetroffen in huidige implementatie. Interactie verloopt via standaard tabellen, links en formulieren.

---

## 6. REST API

Er zijn in de huidige versie geen custom REST-endpoints geregistreerd. De plugin gebruikt:

- WordPress hooks
- admin-post acties
- shortcode rendering

### Endpointoverzicht

| Endpoint | Methode | Status |
|---|---|---|
| custom REST endpoint | n.v.t. | Niet geimplementeerd |

### Parameters

| Parameter | Bron | Toelichting |
|---|---|---|
| TeamId | querystring | Filter op team in scoreoverzichten |
| SurvivalId | querystring | Filter op survival in scoreoverzichten |
| ScoreId | querystring | Doelrecord voor score-updateformulier |

### JSON-responsvoorbeeld

```json
{
	"status": "n.v.t.",
	"message": "Geen custom REST API in deze pluginversie"
}
```

### Interactiesequentie (zonder REST)

```mermaid
sequenceDiagram
		participant U as Gebruiker
		participant WP as WordPress
		participant SC as Shortcode Callback
		participant DB as Plugin Tabellen

		U->>WP: Open pagina met shortcode
		WP->>SC: Start callback
		SC->>DB: Lees records via query
		DB-->>SC: Resultset
		SC-->>WP: Gerenderde HTML
		WP-->>U: Pagina-output
```

---

## 7. Bedrijfslogica

### 7.1 Berekeningen

Positionele score per survival:

$$
P_{basis}(positie) = N_{teams} - positie
$$

Jokercorrectie:

$$
P_{effectief} =
\begin{cases}
2 \cdot P_{basis} & \text{als joker = 1} \\
P_{basis} & \text{als joker = 0}
\end{cases}
$$

Teamtotaal:

$$
T_{team} = \sum_{i=1}^{k} P_{effectief,i}
$$

Voorbeeld (20 teams):

| Positie | Basispunten | Joker | Effectieve punten |
|---|---:|---:|---:|
| 1 | 19 | 0 | 19 |
| 2 | 18 | 1 | 36 |
| 5 | 15 | 0 | 15 |
| 10 | 10 | 0 | 10 |

### 7.2 Validatie

```mermaid
flowchart LR
		A[Open team_score_update] --> B{Ingelogd als referee/admin?}
		B -- Nee --> C[Toon foutmelding]
		B -- Ja --> D[Lees ScoreId]
		D --> E{POST update?}
		E -- Nee --> F[Toon formulier]
		E -- Ja --> G[Valideer velden en format tijd]
		G --> H[Update score record]
		H --> I[Toon bevestiging]
```

Methodetabel validatie/controle:

| Methode | Doel |
|---|---|
| validUserRole() | Rolcontrole referee/administrator |
| check_user_role($role) | Controle op WordPress gebruikersrol |
| teamScoreUpdate() | Validatie en opslag scoreformulier |
| register_setting() | Definieert en registreert URL-instellingen |

---

## 8. Klassenstructuur

```mermaid
classDiagram
		class Survival {
			-loader
			-plugin_name
			-version
			+run()
			+get_plugin_name()
			+get_loader()
			+get_version()
		}

		class Survival_Loader {
			-actions
			-filters
			+add_action()
			+add_filter()
			+run()
		}

		class Survival_i18n {
			+load_plugin_textdomain()
		}

		class Survival_Admin {
			-plugin_name
			-version
			-option_name
			+add_options_page()
			+add_management_page()
			+register_setting()
			+calculateScore()
			+displayScore()
			+teamScoreUpdate()
			+survivalTeamScore()
			+teamScoreAllSurvival()
			+survivalListAll()
			+teamListAll()
			+refereeListAll()
			+refereeSurvivalListAll()
		}

		class Survival_Public {
			-plugin_name
			-version
			+enqueue_styles()
			+enqueue_scripts()
			+display_survival_help()
			+display_survival_page()
			+display_survival_01_page()
			+display_survival_11_page()
		}

		class Survival_Activator {
			+activate()
			+createTable_Team()
			+createTable_Survival()
			+createTable_Referee()
			+createTable_TimeSlot()
			+createTable_Score()
			+initValue_*( )
			+add_survival_role()
		}

		class Survival_Deactivator {
			+deactivate()
			+dropTable_*( )
			+remove_survival_role()
		}

		Survival --> Survival_Loader
		Survival --> Survival_i18n
		Survival --> Survival_Admin
		Survival --> Survival_Public
		Survival --> Survival_Activator
		Survival --> Survival_Deactivator
```

---

## 9. Activatie en Deinstallatie

### Activatieflow

```mermaid
flowchart TD
		A[Plugin activatie] --> B[Maak tabellen aan]
		B --> C[Vul initiele data]
		C --> D[Voeg rol referee toe]
		D --> E[Bewaar DB versie optie]
```

### Bootstrap-volgorde

```mermaid
sequenceDiagram
		participant WP as WordPress
		participant BOOT as survival.php
		participant CORE as Survival
		participant LOAD as Survival_Loader

		WP->>BOOT: Laad pluginbestand
		BOOT->>BOOT: Register activate/deactivate hooks
		BOOT->>CORE: new Survival()
		CORE->>LOAD: Registreer alle hooks
		BOOT->>CORE: run()
		CORE->>LOAD: run hooks
```

### Deinstallatiestappen

| Trigger | Gedrag |
|---|---|
| Deactivatie | Dropt tabellen score/referee/team/survival/timeslot en verwijdert rol referee |
| Uninstall | Alleen WP_UNINSTALL_PLUGIN check; geen extra cleanup geïmplementeerd |

---

## 10. Assets en Scripts

### Overzicht assets

| Asset | Locatie | Gebruik |
|---|---|---|
| Admin CSS | admin/css/survival-admin.css | Layout van settings/tools en tabellen |
| Admin JS | admin/js/survival-admin.js | Admin interactie (basis) |
| Public CSS | public/css/survival-public.css | Frontend styling voor score/help pagina's |
| Public JS | public/js/survival-public.js | Frontend interactie (basis) |
| Help partials | public/partials/*.php | Inhoud survival-help per taal/pagina |

### Flow: score-update knop

```mermaid
flowchart TD
		A[Klik Update link bij scorerecord] --> B[Open team_score_update met ScoreId]
		B --> C[Toon formuliervelden]
		C --> D[Klik Update submit]
		D --> E[Update in wp_score]
		E --> F[Toon bevestiging]
```

### Flow: frontend scorelogica

```mermaid
flowchart TD
		A[Gebruiker selecteert TeamId/SurvivalId] --> B[Shortcode callback]
		B --> C[Query score + join team/survival/timeslot]
		C --> D[Render HTML tabel]
		D --> E[Optionele vervolgklik naar detail/update]
```

---

## 11. Gebruikersrollen en toegang

### Toegangsdiagram

```mermaid
flowchart TD
		A[Gebruiker] --> B{Rol?}
		B -- administrator --> C[Settings + Tools + Update Score]
		B -- referee --> D[Update Score + score-overzichten]
		B -- anoniem --> E[Publieke score- en help-pagina's]
```

### Samenvatting rechten

| Rol | Toegang |
|---|---|
| administrator | Volledige toegang tot instellingen, tools en scoreberekening |
| referee | Scoremutatie via shortcodeformulier en lezen van overzichten |
| anonieme bezoeker | Alleen leesbare score- en helpweergaven |

### Functionele aandachtspunten

| Punt | Impact |
|---|---|
| Joker-weergave in teamScoreAllSurvival gebruikt niet het huidige recordobject | Onjuiste ja/nee-weergave mogelijk |
| survival_12_page wordt functioneel verwacht maar niet als shortcode geregistreerd | Onvolledige helpnavigatie |
| Schema en planning zijn hardcoded op 20 teams / 12 survivals / 12 slots | Beperkte schaalbaarheid zonder codewijziging |

---

*Gegenereerd op 5 juli 2026 · BSO Survival v1.0.0*
