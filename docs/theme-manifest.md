# Theme Manifest — moneyweb-theme.json

Hvert Child Theme skal have en `moneyweb-theme.json` i teme-roden.
Dette er kontrakten mellem Child Theme og moneyweb-core.

---

## Placering

```
moneyweb-handvaerker-01/
├── moneyweb-theme.json
├── style.css
├── functions.php
└── ...
```

---

## Format

```json
{
  "theme": "moneyweb-handvaerker-01",
  "theme_version": "1.0.0",
  "schema_version": 1,
  "global": [
    { "key": "company_name",    "type": "text",  "required": true,  "label": "Virksomhedsnavn" },
    { "key": "company_phone",   "type": "text",  "required": true,  "label": "Telefon" },
    { "key": "company_email",   "type": "text",  "required": true,  "label": "E-mail" },
    { "key": "company_address", "type": "text",  "required": false, "label": "Adresse" },
    { "key": "logo_primary",    "type": "image", "required": false, "label": "Logo" },
    { "key": "facebook_url",    "type": "text",  "required": false, "label": "Facebook URL" },
    { "key": "instagram_url",   "type": "text",  "required": false, "label": "Instagram URL" }
  ],
  "pages": {
    "home": {
      "title": "Forside",
      "slug": "forside",
      "template": "front-page.php",
      "is_front_page": true,
      "label": "Forside",
      "fields": [
        { "key": "hero_heading",          "type": "text",    "required": true,  "label": "Hero overskrift" },
        { "key": "hero_intro",            "type": "wysiwyg", "required": false, "label": "Hero introduktionstekst" },
        { "key": "hero_background_image", "type": "image",   "required": false, "label": "Hero baggrundsbillede" },
        {
          "key": "hero_checklist",
          "type": "repeater",
          "required": false,
          "label": "Hero checkliste",
          "sub_fields": [
            { "key": "text", "type": "text", "required": true, "label": "Punkt" }
          ]
        },
        { "key": "about_heading", "type": "text",    "required": false, "label": "Om os overskrift" },
        { "key": "about_text",    "type": "wysiwyg", "required": false, "label": "Om os tekst" },
        { "key": "about_image",   "type": "image",   "required": false, "label": "Om os billede" }
      ]
    },
    "about": {
      "title": "Om os",
      "slug": "om-os",
      "template": "page-about.php",
      "label": "Om os",
      "fields": [
        { "key": "hero_heading",          "type": "text",    "required": true,  "label": "Overskrift" },
        { "key": "hero_background_image", "type": "image",   "required": false, "label": "Baggrundsbillede" },
        { "key": "content_heading",       "type": "text",    "required": false, "label": "Indhold overskrift" },
        { "key": "content_body",          "type": "wysiwyg", "required": false, "label": "Indhold tekst" }
      ]
    },
    "contact": {
      "title": "Kontakt",
      "slug": "kontakt",
      "template": "page-contact.php",
      "label": "Kontakt",
      "fields": [
        { "key": "heading",          "type": "text",    "required": true,  "label": "Overskrift" },
        { "key": "text",             "type": "wysiwyg", "required": false, "label": "Tekst" },
        { "key": "background_image", "type": "image",   "required": false, "label": "Baggrundsbillede" }
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
| `repeater` | Liste af elementer — kræver `sub_fields` |
| `number` | Tal |

---

## Regler

1. `theme` skal matche Child Theme's directory-navn præcist
2. Alle feltnøgler (`key`) må kun indeholde bogstaver, tal og underscore
3. Feltnøgler skal være unikke inden for samme page
4. Hver side skal have `title`, `slug` og `template`
5. `global`-felter gemmes på ACF Options Page — ikke på en specifik WordPress-side
6. `schema_version` øges kun ved breaking changes

---

## Versioneringsregel

| Ændring | Schema version |
|---|---|
| Tilføj nyt valgfrit felt | Uændret |
| Tilføj nyt obligatorisk felt | +1 |
| Fjern et felt | +1 |
| Omdøb et felt | +1 |
| Ændr felttype | +1 |

---

## Hvad moneyweb-core gør med manifestet

**Sider:**
Core finder eller opretter WordPress-sider baseret på `title`, `slug` og `template`:
1. Søg efter side med meta `_moneyweb_page_key = [page_key]`
2. Ellers søg efter side med `post_name = [slug]`
3. Ellers opret siden med `title` og `slug`
4. Gem `_moneyweb_page_key` som post meta

**Template-håndtering:**
- For almindelige sider (ikke front page) sættes `_wp_page_template = [template]`
- For sider med `"is_front_page": true` sættes IKKE `_wp_page_template`. I stedet sættes:
  ```php
  update_option('show_on_front', 'page');
  update_option('page_on_front', $page_id);
  ```
  WordPress vælger automatisk `front-page.php` fra det aktive theme.

**ACF location-regler:**

| Scope | ACF location |
|---|---|
| Globale felter | `options_page == moneyweb-settings` |
| Home (front page) | `page_type == front_page` |
| Andre sider | `page_template == [template-filnavn]` |

Eksempler: `page_template == page-about.php`, `page_template == page-contact.php`

**ACF-feltgrupper:**
Core registrerer feltgrupper med deterministiske, stabile keys:

| Scope | Group key | Field key eksempel |
|---|---|---|
| Global | `group_mw_global` | `field_mw_global_company_name` |
| Home | `group_mw_home` | `field_mw_home_hero_heading` |
| Repeater sub-field | — | `field_mw_home_hero_checklist_text` |

Format: `field_mw_{page}_{field_key}` — aldrig tilfældigt genereret.

**Image-felter:**
Registreres med `return_format: id`. Core downloader billed-URL, importerer til media library og gemmer attachment-ID via `update_field()`.

**Globale felter:**
Gemmes med `update_field($field_key, $value, 'option')` på ACF Options Page med slug `moneyweb-settings`.
