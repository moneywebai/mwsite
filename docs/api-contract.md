# API Contract — moneyweb-core

Version: 1.0
Namespace: `moneyweb/v1`
Base URL: `https://[subsite].mwsite.dk/wp-json/moneyweb/v1`

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

Alle routes registreres på `rest_api_init` og har `permission_callback` der validerer API-key:

```php
add_action('rest_api_init', function() {
    register_rest_route('moneyweb/v1', '/schema', [
        'methods'             => 'GET',
        'callback'            => [Moneyweb_Schema::class, 'handle'],
        'permission_callback' => [Moneyweb_Auth::class, 'check'],
    ]);
    register_rest_route('moneyweb/v1', '/site-data', [
        'methods'             => 'POST',
        'callback'            => [Moneyweb_Site_Data::class, 'handle'],
        'permission_callback' => [Moneyweb_Auth::class, 'check'],
    ]);
});
```

---

## GET /schema

Returnerer det aktive Child Theme's feltskema til n8n.

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
  "schema_version": 1,
  "global": [
    { "key": "company_name",  "type": "text",  "required": true,  "label": "Virksomhedsnavn" },
    { "key": "company_phone", "type": "text",  "required": true,  "label": "Telefon" },
    { "key": "company_email", "type": "text",  "required": true,  "label": "E-mail" },
    { "key": "logo_primary",  "type": "image", "required": false, "label": "Logo" }
  ],
  "pages": {
    "home": {
      "title": "Forside",
      "slug": "forside",
      "template": "front-page.php",
      "fields": [
        { "key": "hero_heading",          "type": "text",    "required": true,  "label": "Hero overskrift" },
        { "key": "hero_intro",            "type": "wysiwyg", "required": false, "label": "Introduktionstekst" },
        { "key": "hero_background_image", "type": "image",   "required": false, "label": "Baggrundsbillede" },
        {
          "key": "hero_checklist",
          "type": "repeater",
          "required": false,
          "label": "Checkliste",
          "sub_fields": [
            { "key": "text", "type": "text", "required": true, "label": "Punkt" }
          ]
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

### Response 404

```json
{ "status": "error", "code": "no_manifest", "message": "Active theme has no moneyweb-theme.json" }
```

### Response 503

```json
{ "status": "error", "code": "acf_not_active", "message": "ACF Pro is required but not active" }
```

---

## POST /site-data

Modtager indhold fra n8n og gemmer via ACF.

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
  "schema_version": 1,
  "global": {
    "company_name":    "Hansen VVS ApS",
    "company_phone":   "12 34 56 78",
    "company_email":   "kontakt@hansenvvs.dk",
    "company_address": "Borgergade 12, 2100 København Ø",
    "logo_primary":    "https://example.com/logo.png"
  },
  "pages": {
    "home": {
      "hero_heading": "Din lokale VVS-mester",
      "hero_intro":   "<p>Vi løser alle VVS-opgaver hurtigt og til fast pris.</p>",
      "hero_background_image": "https://example.com/hero.jpg",
      "hero_checklist": [
        { "text": "Altid fast pris" },
        { "text": "Hurtig respons" },
        { "text": "30 års erfaring" }
      ]
    },
    "about": {
      "hero_heading":   "Om Hansen VVS",
      "content_body":   "<p>Vi har hjulpet københavnere siden 1994...</p>"
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
| `repeater` | array af objekter med sub-field-nøgler |
| `number` | number |

### Response 200

```json
{
  "status": "ok",
  "saved": {
    "global": 5,
    "pages": { "home": 4, "about": 2 }
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

`warnings` er altid til stede i response — tom array hvis ingen advarsler.

### Response 400 — Valideringsfejl

```json
{
  "status": "error",
  "code": "validation_failed",
  "errors": [
    {
      "code": "theme_mismatch",
      "message": "Payload theme 'moneyweb-handvaerker-01' does not match active theme 'moneyweb-revisor-01'"
    },
    {
      "code": "schema_version_mismatch",
      "message": "Expected schema_version 2, got 1"
    },
    {
      "code": "required_field_missing",
      "field": "company_name",
      "message": "Required field missing"
    }
  ]
}
```

### Response 401

```json
{ "status": "error", "code": "unauthorized", "message": "Invalid API key" }
```

### Response 503

```json
{ "status": "error", "code": "acf_not_active", "message": "ACF Pro is required but not active" }
```

---

## Valideringsrækkefølge

1. ACF Pro aktiv
2. API-key
3. `theme` matcher aktivt Child Theme
4. `schema_version` matcher manifest
5. Alle `required: true` felter til stede
6. Ukendte felter samles i `warnings` og springes over — ingen fejl

---

## ACF field key-navngivning

Keys er deterministiske og stabile — aldrig tilfældigt genereret:

| Scope | Group key | Field key |
|---|---|---|
| Global options | `group_mw_global` | `field_mw_global_company_name` |
| Page: home | `group_mw_home` | `field_mw_home_hero_heading` |
| Repeater sub-field | — | `field_mw_home_hero_checklist_text` |

Format: `field_mw_{page}_{field_key}` for side-felter, `field_mw_global_{field_key}` for globale felter.

Feltgrupper registreres via `acf_add_local_field_group()` på `acf/init`-hook — ikke på `init`.
