# Arkitektur — Moneyweb WordPress Platform

## Overblik

```
WordPress Multisite (mwsite.dk / mw1.dk)
│
├── moneyweb-core (plugin)
│   └── Læser manifest → registrerer ACF → API endpoints
│
├── ACF Pro (plugin)
│   └── Feltlagring
│
├── moneyweb-base (parent theme)
│   └── Fælles fundament
│
└── moneyweb-[design] (child theme)
    ├── moneyweb-theme.json   ← kontrakt med Core
    ├── templates/
    ├── style.css
    └── functions.php
```

---

## Komponenter

### moneyweb-core

Det eneste plugin i systemet (udover ACF Pro).

**Gør præcis dette:**
1. Finder og læser aktivt Child Theme's `moneyweb-theme.json`
2. Registrerer de ACF-felter manifestet kræver
3. Tilbyder `GET /wp-json/moneyweb/v1/schema`
4. Tilbyder `POST /wp-json/moneyweb/v1/site-data`
5. Validerer den modtagne JSON mod manifestet
6. Gemmer feltværdier via ACF

**Gør ikke:**
- Registrerer ikke CPT'er (ingen CPT'er i fase 1)
- Indeholder ikke bookinglogik, kundepanel eller andre features
- Indeholder ikke avanceret HMAC — simpel API-key er tilstrækkeligt nu

### moneyweb-base

Parent Theme. Installeres på alle Multisite-sites som parent.

**Indeholder:**
- `functions.php` med theme setup og nav-registrering
- `header.php` og `footer.php` som Child Themes kan bruge eller overskrive
- Mobilnavigation (vanilla JS)
- CSS-reset og basisvariabler (`--mw-color-primary`, `--mw-space-md`, osv.)
- Container- og spacing-system
- Få helper-funktioner til Child Themes

**Indeholder ikke:**
- Branchespecifikke designs
- Funktioner eller forretningslogik
- API-endpoints

### Child Theme (f.eks. moneyweb-handvaerker-01)

Et Child Theme pr. design. Kun ét aktivt pr. site.

**Indeholder:**
- `moneyweb-theme.json` — manifestet der beskriver feltbehov
- `style.css` — child theme header + design-specifikke CSS-variabler
- `functions.php` — child theme setup
- Templates: `front-page.php`, `page-about.php`, `page-contact.php` osv.
- CSS og JavaScript til det konkrete design
- PHP-rendering af ACF-felter

**Indeholder ikke:**
- API-logik
- Databaseskrivning
- Forretningslogik
- Custom Post Types

### ACF Pro

Feltlagring. Feltgrupper registreres af moneyweb-core via PHP baseret på manifestet.
Ingen feltgrupper oprettes manuelt i ACF UI for nye sites.

### Custom Post Types

**Fase 1:** Ingen Custom Post Types. Ydelser og lignende håndteres via repeater-felter i Child Theme's manifest.

**Senere:** CPT'er registreres i moneyweb-core eller separate plugins — aldrig i Child Themes. Data må ikke afhænge af aktivt design.

### n8n

Automatiseringsmotor. Kører det eksisterende flow med tilpasning til det nye API.

---

## Dataflow

### Oprettelse af nyt site

```
n8n
 │
 ├─1─► WP Multisite API: opret subsite
 ├─2─► WP API: aktivér child theme
 ├─3─► GET /moneyweb/v1/schema  →  Core læser moneyweb-theme.json  →  returnerer feltliste
 ├─4─► AI (ChatGPT): generér indhold baseret på schema + virksomhedsdata
 └─5─► POST /moneyweb/v1/site-data  →  Core validerer  →  ACF gemmer  →  Child Theme renderer
```

### Kunde besøger sitet

```
Browser → WordPress → Child Theme template → get_field() fra ACF → HTML
```

---

## Filstruktur i repository

```
moneyweb-wp/
├── CLAUDE.md
├── docs/
│   ├── architecture.md          (denne fil)
│   ├── api-contract.md
│   ├── theme-manifest.md
│   ├── phase-1.md
│   └── legacy/
│       └── acf-export-2026-06-21.json
├── plugins/
│   └── moneyweb-core/
└── themes/
    ├── moneyweb-base/
    └── moneyweb-handvaerker-01/  (fase 2)
```

---

## Miljø

| Miljø | Domæne | Status |
|---|---|---|
| Produktion | mw1.dk | Må ikke røres i fase 1 |
| Test/dev | mwsite.dk | Al udvikling og test sker her |

Den gamle ACF-eksport (`docs/legacy/`) bruges som reference ved eventuel fremtidig migration af eksisterende Oxygen-sites. Den blokerer ikke det nye system.
