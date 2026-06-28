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
1. Definerer en fast liste af Core-felter (se `core-fields.md`) — sandhedskilde for fælles globale felter
2. Eksponerer Core API-version som konstanten `MONEYWEB_SCHEMA_VERSION` (pt. 2) — uafhængig af themets schema-version
3. Finder og læser aktivt Child Theme's `moneyweb-theme.json` (theme-specifikke felter)
4. Validerer at theme-manifestet ikke kolliderer med Core (reserved keys) og at hvert top-level felt definerer en gyldig `automation.action`
5. Registrerer ACF-feltgrupper: `group_mw_core_global` + `group_mw_theme_global` + `group_mw_{page}`
6. Tilbyder `GET /wp-json/moneyweb/v1/schema` — kombineret schema med `source`/`target`/`automation` på hvert felt + dual versioning (`schema_version` + `theme_schema_version`)
7. Tilbyder `POST /wp-json/moneyweb/v1/site-data` — flat payload, kræver begge versioner, router internt via `source`
8. Validerer den modtagne JSON mod det kombinerede schema
9. Gemmer feltværdier via ACF og rapporterer pr. felt: `updated` / `unchanged` / `failed`

**Gør ikke:**
- Registrerer ikke CPT'er (ingen CPT'er i fase 1)
- Indeholder ikke bookinglogik, kundepanel eller andre features
- Udfører ikke automation-handlinger — den beskriver kun behovet i `automation` på hvert felt
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
│   ├── core-fields.md           (fase 1.1)
│   ├── phase-1.md               (historisk)
│   ├── phase-1.1.md             (aktuel)
│   └── legacy/
│       └── acf-export-2026-06-21.json
├── plugins/
│   └── moneyweb-core/
│       ├── moneyweb-core.php
│       └── includes/
│           ├── class-core-fields.php   (Core-fields sandhedskilde)
│           ├── class-manifest.php
│           ├── class-acf-builder.php
│           ├── class-auth.php
│           ├── class-schema.php        (build_combined + REST)
│           ├── class-validator.php
│           └── class-site-data.php
└── themes/
    ├── moneyweb-base/
    ├── moneyweb-test-01/        (testtheme — fase 1/1.1)
    └── moneyweb-handvaerker-01/ (fase 2)
```

---

## Miljø

| Miljø | Domæne | Status |
|---|---|---|
| Produktion | mw1.dk | Må ikke røres i fase 1 |
| Test/dev | mwsite.dk | Al udvikling og test sker her |
| Provisioning-/integrationstestsite | fase11test.mwsite.dk (blog 414) | Permanent — bruges til Multisite-isolations- og provisioning-tests |

Den gamle ACF-eksport (`docs/legacy/`) bruges som reference ved eventuel fremtidig migration af eksisterende Oxygen-sites. Den blokerer ikke det nye system.

---

## Multisite — HTTPS og subsite-URL

Hvert subsite har sit eget `siteurl` og `home` i `wp_{blog_id}_options`. WordPress `wp site create` på en HTTP-installation arver HTTP for nye sites' siteurl/home — selv når hovedsitet og resten af netværket bruger HTTPS. Det skal eksplicit rettes til HTTPS efter oprettelsen.

**Regel for fase 2 og frem:**
Alle nye Moneyweb-subsider skal have HTTPS i `siteurl` og `home` straks ved oprettelsen. Ellers genererer WP HTTP-canonical-URLs, HTTP `Link`-headers (rel=api), og blandet HTTPS/HTTP-flow i `wp-json`-resultatet.

**Hvor det skal håndteres:**

| Mulighed | Vurdering |
|---|---|
| Direkte i WP Multisite `wpmu_new_blog`-hook | Simplest. Hooket fyrer lige efter `wp site create`. Et lille filter i `moneyweb-core` der sætter `siteurl`/`home` til HTTPS-form sikrer at *alle* nye subsider får det — uanset om de oprettes via WP-CLI, admin, eller fremtidige flows. |
| I et kommende `/provision`-endpoint | Mere kontrolleret, men dækker kun sites oprettet via det endpoint. Kræver at andre oprettelsesveje (WP admin, WP-CLI manuelt) får et separat fix. |

**Anbefaling:** Læg HTTPS-normaliseringen i en `wpmu_new_blog`-hook i `moneyweb-core` — så er sikkerheden ufravigelig. `/provision`-endpointet kan stadig orkestrere temaaktivering, API-key-oprettelse og initial side-provisioning ovenpå. Implementeres ikke nu.

---

## Multisite — Media URL

Uploads gemmes pr. subsite under den eksisterende install:
```
/wp-content/uploads/sites/{blog_id}/{år}/{måned}/{filnavn}
```

Den offentlige URL bruger aktuelt hovedsitets domæne:
```
https://mwsite.dk/wp-content/uploads/sites/{blog_id}/...
```

**Er dette normal WP Multisite-adfærd?** Ja. WordPress Multisite genererer som standard upload-URLs ud fra `WP_CONTENT_URL`, som er bundet til hoveddomænet på subdomain-installs. Subsites' egne domæner peger ikke på `/wp-content/`-stien medmindre hver subdomain har sit eget vhost med matching docroot — det er ikke tilfældet her (alle subsider serveres af samme PHP-app via wildcard DNS + delt docroot).

**Teknisk grund til senere at bruge kundens subdomæne i stedet?**
- **SEO:** Billed-URLs på kundens eget domæne giver lidt bedre image SEO og signalerer site-cohesion.
- **CSP / mixed-host warnings:** Kundens browser ser billeder fra et andet domæne end siden — typisk ingen reel CORS/CSP-problemer, men kunne genere CSP-konfigurationer.
- **White-label / branding:** "Powered by mwsite.dk" lækker visuelt via image-URLs.
- **Caching/CDN:** Et per-kunde-domæne kunne lettere mappes til separat CDN-konfiguration.

Modargumenter: kræver enten et `upload_dir`-filter der omskriver URL eller en serverside-reverse-proxy/regel der serverer `/wp-content/uploads/sites/{blog_id}/` fra kundens domæne. Begge løsninger er overkommelige men ikke trivielle.

**Status:** Ingen ændring nu. Genovervejes hvis SEO/branding bliver et reelt issue.
