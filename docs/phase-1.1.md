# Fase 1.1 — Core fields og automation-aware schema

Bygger oven på `phase-1.md` (historisk arkiv). Indfører faste Core-felter, automation-metadata, `target`/`source` på hvert schemafelt, reserved-key collision check og struktureret `opening_hours`.

---

## Hvad er nyt

| Område | Ændring |
|---|---|
| **Core fields** | Ny klasse `Moneyweb_Core_Fields` — eneste sandhedskilde for fælles globale felter |
| **ACF-registrering** | `group_mw_core_global` (Core) + `group_mw_theme_global` (theme extras, omdøbt fra `group_mw_global`) |
| **Reserved keys** | Et theme der bruger en Core-key giver HTTP 422 — ingen partial schema returneres |
| **Combined schema** | `Moneyweb_Schema::build_combined()` samler Core + theme, annoterer hvert felt med `source` og `target` |
| **`source`** | `"core"` eller `"theme"` — pr. felt i `/schema`-response |
| **`target`** | Dot-path i POST-payloaden — fx `global.company_name`, `pages.home.hero_heading` |
| **Automation** | `automation: { action, … }` pr. felt — Core registrerer, n8n udfører |
| **Manifest-metadata** | `customer_editable`, `description`, `default`, `automation` accepteres (alle optional) |
| **`color`-type** | Ny felttype, registreres som ACF `color_picker`, sanitizer er `#rgb`/`#rrggbb`/`#rrggbbaa` |
| **`opening_hours`** | Repeater med `day`, `open`, `close`, `closed`, `note` — ikke wysiwyg |

API-formatet er **flat og enkelt** — ingen separate `core`/`theme`/`features`-top-level blokke. `global` indeholder Core og theme-felter sammen, hver med `source`.

---

## Filændringer

**Nyt:**
- `plugins/moneyweb-core/includes/class-core-fields.php`
- `docs/core-fields.md`
- `docs/phase-1.1.md` (denne fil)

**Ændret:**
- `plugins/moneyweb-core/moneyweb-core.php` — loader Core fields, registrerer Core-gruppe altid, admin-notice ved invalid manifest
- `plugins/moneyweb-core/includes/class-acf-builder.php` — `register_core_fields()`, color-type, omdøbt theme global gruppe
- `plugins/moneyweb-core/includes/class-schema.php` — `build_combined()` (Single source of truth for /schema, validator, site-data)
- `plugins/moneyweb-core/includes/class-validator.php` — bruger combined schema, mister theme-only globals-koncept
- `plugins/moneyweb-core/includes/class-site-data.php` — router global-saves via `source`, color-type understøttet
- `themes/moneyweb-test-01/moneyweb-theme.json` — fjernet duplikerede Core-keys; tilføjet `primary_color` (color, theme global) + automation på alle felter
- `themes/moneyweb-base/header.php` — `logo_primary` → `logo`

---

## ACF field-key skema (efter fase 1.1)

| Scope | Group key | Field key |
|---|---|---|
| Core (always-on) | `group_mw_core_global` | `field_mw_core_{key}` |
| Theme extras (global) | `group_mw_theme_global` | `field_mw_theme_global_{key}` |
| Page | `group_mw_{page}` | `field_mw_{page}_{key}` |
| Repeater sub | — | `field_mw_{scope}_{key}_{sub_key}` |

---

## Eksempel — combined `/schema`-response

```json
{
  "status": "ok",
  "theme": "moneyweb-test-01",
  "theme_version": "1.0.0",
  "schema_version": 1,
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
        "instruction": "Vælg en professionel farve, der passer til virksomhedens logo og branche."
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
            "instruction": "Skriv en kort overskrift, som forklarer virksomhedens vigtigste fordel.",
            "max_characters": 65,
            "format": "plain_text"
          }
        }
      ]
    }
  }
}
```

---

## n8n-flow (uændret kontrakt, beriget metadata)

```text
n8n opretter subsite
→ n8n aktiverer valgt Child Theme
→ n8n opretter eller modtager API-key
→ n8n kalder GET /schema
→ Core samler Core-felter og felter fra aktivt Child Theme
→ n8n læser target, type og automation.action
→ n8n kopierer onboardingdata (copy_from_onboarding)
→ n8n genererer tekster (generate_text)
→ n8n finder eller genererer billeder (find_or_generate_image)
→ n8n vælger farver (select_color)
→ n8n bygger POST-payloaden ud fra target
→ n8n kalder POST /site-data
→ Core validerer og gemmer alt gennem ACF
```

POST-payloaden bruger feltnøglerne direkte under `global` og `pages` — ingen separat Core/theme-opdeling i payloaden. Core ved internt hvilke der er Core og hvilke der er theme via `source` på det kombinerede schema.

---

## Hvad er IKKE i fase 1.1

- `/provision` endpoint
- Feature-moduler
- Kundekontrolpanel
- API-key UI
- Schema-version-tolerance (forbliver strict match)
- Et rigtigt håndværker-theme
- SSRF-filter på image sideload
- WordPress Site Icon (favicon) integration
- SEO/Analytics/Cookiebot integration
