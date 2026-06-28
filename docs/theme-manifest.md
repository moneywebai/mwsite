# Theme Manifest — moneyweb-theme.json

Hvert Child Theme skal have en `moneyweb-theme.json` i theme-roden.
Det er kontrakten mellem Child Theme og moneyweb-core.

**Vigtigt:** Manifestet beskriver KUN theme-specifikke felter. De fælles felter (kontaktinfo, logo, sociale links, åbningstider, …) ejes af Moneyweb Core — se [`core-fields.md`](core-fields.md). Et theme må ikke duplikere eller definere noget med en Core-key.

---

## Placering

```
moneyweb-handvaerker-01/
├── moneyweb-theme.json
├── style.css
├── functions.php
└── …
```

---

## Format

```json
{
  "theme": "moneyweb-handvaerker-01",
  "theme_version": "1.0.0",
  "schema_version": 1,
  "global": [
    {
      "key": "primary_color",
      "type": "color",
      "required": true,
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
          "type": "text",
          "required": true,
          "label": "Hero overskrift",
          "customer_editable": true,
          "automation": {
            "action": "generate_text",
            "instruction": "Skriv en kort overskrift om virksomhedens vigtigste fordel.",
            "max_characters": 65,
            "format": "plain_text"
          }
        },
        {
          "key": "hero_background_image",
          "type": "image",
          "required": true,
          "label": "Hero-billede",
          "customer_editable": true,
          "automation": {
            "action": "find_or_generate_image",
            "instruction": "Et realistisk billede af virksomhedens primære arbejde. Ingen tekst eller vandmærker.",
            "orientation": "landscape",
            "minimum_width": 1600,
            "minimum_height": 900,
            "avoid": ["tekst i billedet", "vandmærker", "tydeligt kunstigt AI-look"]
          }
        }
      ]
    }
  }
}
```

---

## Felttyper

| Type | Beskrivelse |
|---|---|
| `text` | Enkelt tekstlinje |
| `wysiwyg` | Formateret tekst (HTML) |
| `image` | Billede — sendes som HTTPS URL, Core importerer til media library og gemmer attachment-ID |
| `true_false` | Boolean toggle |
| `number` | Tal |
| `color` | Farve — gemmes som `#rrggbb` / `#rrggbbaa` (ACF `color_picker`) |
| `repeater` | Liste af elementer — kræver `sub_fields` |

---

## Metadata pr. felt

| Felt | Krævet | Beskrivelse |
|---|---|---|
| `key` | ✓ | Unik nøgle inden for samme scope, må ikke kollidere med Core |
| `type` | ✓ | Se felttyper |
| `label` | | Vises som ACF-feltlabel og i Moneyweb-kontrolpanel |
| `required` | | `true` / `false` (default false) |
| `customer_editable` | | Bool — om feltet vises i kundens fremtidige kontrolpanel |
| `description` | | Kort hjælpetekst (vises som hjælpetekst i admin senere) |
| `default` | | Default-værdi der bruges hvis automation er `use_default` |
| `automation` | | Objekt — se nedenfor |
| `sub_fields` | (kun repeater) | Liste af felter med samme format |

Metadata `customer_editable`, `description`, `default` og `automation` passerer uændret gennem `/schema` til n8n.

---

## Automation

Hvert felt kan have et `automation`-objekt der fortæller n8n hvad den skal gøre for at producere værdien. Core udfører ikke handlingen — Core beskriver kun behovet.

```json
"automation": {
  "action": "generate_text",
  "instruction": "…",
  "max_characters": 65,
  "format": "plain_text"
}
```

### Tilladte `action`-værdier

| Action | Når den bruges | Typiske ekstra felter |
|---|---|---|
| `copy_from_onboarding` | Værdi findes i onboardingdata | `onboarding_key` |
| `generate_text` | Tekst der skal AI-genereres | `instruction`, `max_characters`, `format` (plain_text\|html), `tone` |
| `find_image` | Brug eksisterende billedbibliotek/stock | `instruction`, `orientation`, `minimum_width`, `minimum_height`, `avoid` |
| `generate_image` | Generér via image-AI | samme som `find_image` |
| `find_or_generate_image` | Find først, generér som fallback | samme som `find_image` |
| `select_color` | Vælg farve programmatisk | `instruction` |
| `use_default` | Brug `default`-værdi direkte | (læs `default` på feltet) |
| `manual` | Kræver manuelt input — n8n skal ikke fylde feltet | — |

Ugyldig `automation.action` får `/schema` til at returnere HTTP 422 `invalid_automation_action`.

---

## Regler

1. `theme` skal matche Child Theme's directory-navn præcist
2. Alle feltnøgler (`key`) må kun indeholde bogstaver, tal og underscore
3. Feltnøgler skal være unikke inden for samme page
4. Hver side skal have `title`, `slug` og `template`
5. `global`-felter gemmes på ACF Options Page (theme-only — Core-globals hører ikke hjemme her)
6. Et felt må ikke have en `key` der findes i Core's reserverede liste (`company_name`, `company_phone`, `company_email`, `company_address`, `company_cvr`, `logo`, `facebook_url`, `instagram_url`, `linkedin_url`, `opening_hours`, `maps_url`)
7. `schema_version` øges kun ved breaking changes

---

## Versioneringsregel

| Ændring | Schema version |
|---|---|
| Tilføj nyt valgfrit felt | Uændret |
| Tilføj nyt obligatorisk felt | +1 |
| Fjern et felt | +1 |
| Omdøb et felt | +1 |
| Ændr felttype | +1 |

Strict match håndhæves: payload's `schema_version` skal være lig med manifestets.

---

## Hvad moneyweb-core gør med manifestet

**Sider:**
1. Søg efter side med post meta `_moneyweb_page_key = [page_key]`
2. Ellers søg efter side med `post_name = [slug]`
3. Ellers opret med `title` og `slug` (status `publish`)
4. Gem `_moneyweb_page_key` som post meta

**Template-håndtering:**
- For sider med `is_front_page: true` sættes `show_on_front=page` + `page_on_front=[id]` — ikke `_wp_page_template`
- Andre sider får `_wp_page_template = [template]`

**ACF feltgrupper:**

| Scope | Group key | Field key eksempel |
|---|---|---|
| Theme global extras | `group_mw_theme_global` | `field_mw_theme_global_primary_color` |
| Per page | `group_mw_{page}` | `field_mw_home_hero_heading` |
| Repeater sub | — | `field_mw_home_hero_checklist_text` |

**Image-felter:**
- Registreres med `return_format: id`
- Core downloader URL og importerer til media library
- Gemmer attachment-ID via `update_field()`
