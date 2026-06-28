# API Contract — moneyweb-core

Core API version: `MONEYWEB_SCHEMA_VERSION = 2` (fase 1.1)
Namespace: `moneyweb/v1`
Base URL: `https://[subsite].mwsite.dk/wp-json/moneyweb/v1`

**To uafhængige versionsfelter:**
- `schema_version` — versionen af Moneyweb Core/API-kontrakten (defineret af konstanten `MONEYWEB_SCHEMA_VERSION` i pluginnet; pt. **2**)
- `theme_schema_version` — versionen af det aktive themes `moneyweb-theme.json`'s schema (defineret som `schema_version` i theme-manifestet; pt. **1** for `moneyweb-test-01`)

Begge versioner valideres strict ved POST. Manglende eller forkert version → HTTP 400 med `schema_version_mismatch` eller `theme_schema_version_mismatch`.

---

## Forudsætninger

moneyweb-core kontrollerer ved aktivering at ACF Pro er installeret og aktivt.
Hvis ACF Pro ikke er aktivt, vises en tydelig admin-notice og alle API-endpoints returnerer 503.
**Plugin'et deaktiverer sig ikke selv.**

```json
{ "status": "error", "code": "acf_not_active", "message": "ACF Pro is required but not active" }
```

---

## Authentication

Simpel API-key via header:

```
X-Moneyweb-Key: [api_key]
```

API-key gemmes pr. subsite i WordPress options: `moneyweb_api_key`.
Sættes via `update_option('moneyweb_api_key', $key)` — fx af n8n ved site-oprettelse.
Forkert eller manglende key returnerer `401`.

---

## REST-registrering

Routes registreres på `rest_api_init` med `permission_callback` der validerer API-key:

```php
register_rest_route('moneyweb/v1', '/schema',    [ 'methods' => 'GET',  ... ]);
register_rest_route('moneyweb/v1', '/site-data', [ 'methods' => 'POST', ... ]);
```

---

## GET /schema

Returnerer den **kombinerede arbejdsordre** for n8n: Core-felter + det aktive Child Theme's felter, hver annoteret med `source`, `target` og evt. `automation`.

### Request

```
GET /wp-json/moneyweb/v1/schema
X-Moneyweb-Key: [api_key]
```

### Response 200

```json
{
  "status": "ok",
  "theme": "moneyweb-handvaerker-01",
  "theme_version": "1.0.0",
  "schema_version": 2,
  "theme_schema_version": 1,
  "global": [
    {
      "key": "company_name",
      "target": "global.company_name",
      "source": "core",
      "type": "text",
      "required": true,
      "label": "Virksomhedsnavn",
      "customer_editable": true,
      "automation": {
        "action": "copy_from_onboarding",
        "onboarding_key": "company_name"
      }
    },
    {
      "key": "primary_color",
      "target": "global.primary_color",
      "source": "theme",
      "type": "color",
      "required": false,
      "label": "Primær farve",
      "customer_editable": true,
      "automation": {
        "action": "select_color",
        "instruction": "Vælg en professionel farve …"
      }
    }
  ],
  "pages": {
    "home": {
      "title": "Forside",
      "slug": "forside",
      "template": "front-page.php",
      "is_front_page": true,
      "label": "Forside",
      "fields": [
        {
          "key": "hero_heading",
          "target": "pages.home.hero_heading",
          "source": "theme",
          "type": "text",
          "required": true,
          "label": "Hero overskrift",
          "customer_editable": true,
          "automation": {
            "action": "generate_text",
            "instruction": "Skriv en kort overskrift…",
            "max_characters": 65,
            "format": "plain_text"
          }
        }
      ]
    }
  }
}
```

### Response 401

```json
{ "status": "error", "code": "unauthorized", "message": "Invalid API key" }
```

### Response 404 — manifest mangler

```json
{ "status": "error", "code": "no_manifest", "message": "Active theme has no moneyweb-theme.json" }
```

### Response 422 — ugyldigt manifest

Returneres når theme-manifestet bryder Core-kontrakten (reserveret key, ugyldig `automation.action`, …). Der returneres **ingen partial schema** — n8n må ikke modtage et halvt schema.

```json
{
  "status": "error",
  "code": "invalid_manifest",
  "message": "Theme manifest is invalid",
  "errors": [
    {
      "code": "reserved_field_key",
      "field": "company_name",
      "message": "Theme manifest uses a field key reserved by Moneyweb Core."
    }
  ]
}
```

Andre `errors[].code`-værdier i `invalid_manifest`:
- `automation_action_missing` — et top-level felt mangler `automation.action`. Hver Core- og theme-felt skal eksplicit definere en action.
- `invalid_automation_action` — feltets `automation.action` er ikke i den tilladte liste (`copy_from_onboarding`, `generate_text`, `find_image`, `generate_image`, `find_or_generate_image`, `select_color`, `use_default`, `manual`).
- `reserved_field_key` — themes manifest definerer en key der er reserveret af Core.

### Response 503

```json
{ "status": "error", "code": "acf_not_active", "message": "ACF Pro is required but not active" }
```

---

## POST /site-data

Modtager indhold fra n8n. Strukturen er flat — ingen `core` / `theme` / `features` på top-niveau. Core router internt baseret på `source` fra schema'et.

**Bemærk — fase 1.1 scope:** `/site-data` er en **fuld initial-payload-endpoint**. Den kræver at alle obligatoriske felter fra alle sider er til stede i samme request. Partielle kundeopdateringer (fx kun nyt telefonnummer) kræver en særskilt update-mode eller dedikeret endpoint — det er ikke implementeret endnu og bygges i en senere fase.

### Request

```
POST /wp-json/moneyweb/v1/site-data
X-Moneyweb-Key: [api_key]
Content-Type: application/json
```

### Request body

```json
{
  "theme": "moneyweb-handvaerker-01",
  "schema_version": 2,
  "theme_schema_version": 1,
  "global": {
    "company_name":    "Hansen VVS ApS",
    "company_phone":   "12 34 56 78",
    "company_email":   "kontakt@hansenvvs.dk",
    "company_address": "Borgergade 12, 2100 København Ø",
    "logo":            "https://example.com/logo.png",
    "opening_hours": [
      { "day": "Mandag",  "open": "08:00", "close": "16:00" },
      { "day": "Lørdag",  "closed": true,  "note": "Efter aftale" }
    ],
    "primary_color":   "#1a73e8"
  },
  "pages": {
    "home": {
      "hero_heading": "Din lokale VVS-mester",
      "hero_intro":   "<p>Vi løser alle VVS-opgaver hurtigt og til fast pris.</p>",
      "hero_background_image": "https://example.com/hero.jpg",
      "hero_checklist": [
        { "text": "Altid fast pris" },
        { "text": "Hurtig respons" }
      ]
    }
  }
}
```

### Felttyper og format

| Type | Format i JSON |
|---|---|
| `text` | string |
| `wysiwyg` | string — HTML tilladt |
| `image` | HTTPS URL — Core downloader, importerer til media library, gemmer attachment-ID |
| `true_false` | boolean |
| `number` | number |
| `color` | string — `#rgb`, `#rrggbb` eller `#rrggbbaa` |
| `repeater` | array af objekter med sub-field-nøgler |

### Response 200

```json
{
  "status": "ok",
  "result": {
    "global": { "updated": 2, "unchanged": 9, "failed": 0 },
    "pages": {
      "home":    { "updated": 1, "unchanged": 3, "failed": 0 },
      "about":   { "updated": 0, "unchanged": 2, "failed": 0 },
      "contact": { "updated": 0, "unchanged": 2, "failed": 0 }
    }
  },
  "warnings": [
    {
      "code": "unknown_field",
      "page": "home",
      "field": "hero_subtitle",
      "message": "Field not in schema — skipped"
    }
  ]
}
```

Felter pr. scope tælles separat:
- **`updated`** — feltet blev skrevet, og værdien adskilte sig fra den eksisterende.
- **`unchanged`** — den nye værdi er allerede den lagrede værdi. Det er en succes, ikke en fejl. (ACF's `update_field()` returnerer `false` her — vi skelner ved at gen-læse værdien.)
- **`failed`** — feltet kunne ikke gemmes (fx forkert format, image-sideload-fejl). Ledsages typisk af en warning.

`warnings` er altid til stede i responsen — tom array hvis ingen advarsler. Et fejlende felt bliver IKKE til en top-level fejl; det fanges i `result.*.failed` og evt. en warning.

**Idempotency:** Den samme payload kan genkøres trygt — andet kald returnerer `unchanged` for alle felter (verificeret incl. text, wysiwyg, color, repeater og image), og opretter ingen dubletter af sider eller attachments.

For image-felter gemmer Core kilde-URL'en som post-meta `_moneyweb_source_url` på attachment ved oprettelse. Genkør med samme URL → eksisterende attachment genbruges, intet downloades, intet attachment oprettes. Repeater-felter sammenlignes pr. sub-felt-indhold (ikke kun rækkeantal) før de overskrives.

### Response 400 — Valideringsfejl

```json
{
  "status": "error",
  "code": "validation_failed",
  "errors": [
    { "code": "theme_mismatch", "message": "Payload theme '…' does not match active theme '…'" },
    { "code": "schema_version_mismatch", "message": "Expected schema_version 2, got 999" },
    { "code": "theme_schema_version_mismatch", "message": "Expected theme_schema_version 1, got 999" },
    { "code": "required_field_missing", "field": "company_name", "scope": "global", "source": "core", "message": "Required field missing" }
  ]
}
```

### Response 401

```json
{ "status": "error", "code": "unauthorized", "message": "Invalid API key" }
```

### Response 422 — Ugyldigt theme-manifest

Samme respons som `GET /schema` returnerer (hentes via samme `build_combined()`).

### Response 503

```json
{ "status": "error", "code": "acf_not_active", "message": "ACF Pro is required but not active" }
```

---

## Valideringsrækkefølge (POST)

1. ACF Pro aktiv
2. API-key
3. Theme-manifest gyldigt (ikke reserved-key collision, gyldige + tilstedeværende automation actions)
4. `theme` matcher aktivt Child Theme
5. `schema_version` (Core API) matcher `MONEYWEB_SCHEMA_VERSION` (strict)
6. `theme_schema_version` matcher manifest (strict)
7. Alle `required: true` felter (Core + theme) til stede
8. Ukendte felter samles i `warnings` og springes over — ingen fejl

---

## ACF field key-navngivning

Keys er deterministiske og stabile — aldrig tilfældigt genereret:

| Scope | Group key | Field key |
|---|---|---|
| Core (always-on) | `group_mw_core_global` | `field_mw_core_company_name` |
| Theme global extras | `group_mw_theme_global` | `field_mw_theme_global_primary_color` |
| Page | `group_mw_home` | `field_mw_home_hero_heading` |
| Repeater sub | — | `field_mw_home_hero_checklist_text` |

Feltgrupper registreres via `acf_add_local_field_group()` på `acf/init` — ikke `init`.

Site-data writer router globals via det enkelte felts `source`:
- `source: "core"` → `field_mw_core_{key}` (gemmes på options-page)
- `source: "theme"` → `field_mw_theme_global_{key}` (gemmes på options-page)
- Page-felter → `field_mw_{page}_{key}` (gemmes på det enkelte post-ID)
