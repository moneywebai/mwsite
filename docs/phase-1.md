# Fase 1 — Byggeplan

Mål: Den mindste stabile løsning der gør hele flowet muligt.
Miljø: mwsite.dk — mw1.dk røres ikke.

---

## Scope

**Bygges:**
- `moneyweb-core` plugin
- `moneyweb-base` parent theme
- `moneyweb-test-01` testtheme (minimalt Child Theme til fase 1-test)
- End-to-end test med n8n

**Bygges ikke:**
- Rigtige Child Themes (bygges i fase 2 — starter med `moneyweb-handvaerker-01`)
- Custom Post Types (ingen CPT'er i fase 1 — ydelser håndteres via repeater-felter)
- Kundekontrolpanel
- Booking, prisberegnere, funktionsplugins
- Migration af eksisterende Oxygen-sites

---

## A. moneyweb-core

**Placering:** `plugins/moneyweb-core/`

**Filstruktur:**

```
moneyweb-core/
├── moneyweb-core.php          # Plugin header, bootstrap, autoload
└── includes/
    ├── class-manifest.php     # Finder og parser moneyweb-theme.json
    ├── class-acf-builder.php  # Registrerer ACF-feltgrupper fra manifest
    ├── class-auth.php         # API-key authentication
    ├── class-schema.php       # GET /moneyweb/v1/schema
    ├── class-site-data.php    # POST /moneyweb/v1/site-data
    └── class-validator.php    # Validerer payload mod manifest
```

**Implementeringsrækkefølge:**

1. Plugin header og autoload
2. ACF Pro-check ved aktivering — admin notice hvis ACF mangler (plugin deaktiverer sig IKKE)
3. `class-manifest.php` — find og parse `moneyweb-theme.json` fra aktivt theme
4. `class-acf-builder.php` — registrér ACF-feltgrupper på `acf/init` med stabile keys
5. Options Page "Moneyweb Indstillinger" til globale felter
6. `class-auth.php` — API-key check
7. REST routes på `rest_api_init` med `permission_callback`
8. `class-schema.php` — returnerer manifest som JSON
9. `class-validator.php` — validerer payload, samler warnings
10. `class-site-data.php` — finder/opretter sider, gemmer via ACF, håndterer billeder

**Sidehåndtering (class-site-data.php):**

For hver page-nøgle i payload:
1. Find side med post meta `_moneyweb_page_key = [page_key]`
2. Ellers find side med `post_name = [slug fra manifest]`
3. Ellers opret side med `title` og `slug` fra manifest
4. For sider med `is_front_page: true` — sæt `show_on_front` og `page_on_front`, men IKKE `_wp_page_template`
5. For øvrige sider — sæt `_wp_page_template` til template-filnavn
6. Gem `_moneyweb_page_key` som post meta
7. Gem ACF-feltværdier med `update_field()`

**ACF field key-navngivning:**

```
group_mw_global                        → options page feltgruppe
group_mw_{page}                        → pr. side, fx group_mw_home
field_mw_global_{key}                  → fx field_mw_global_company_name
field_mw_{page}_{key}                  → fx field_mw_home_hero_heading
field_mw_{page}_{repeater}_{subkey}    → fx field_mw_home_hero_checklist_text
```

Feltgrupper registreres med `acf_add_local_field_group()` på `acf/init`-hook.
Keys er altid deterministiske — aldrig tilfældigt genereret.

**Image-felter:**
- Registreres med `return_format: id`
- Core downloader HTTPS URL med `media_sideload_image()`
- Gemmer attachment-ID via `update_field()`

**Globale felter:**
- Options Page registreres med `acf_add_options_page()`
- Gemmes med `update_field($field_key, $value, 'option')`
- Hentes med `get_field($field_key, 'option')`

---

## B. moneyweb-base

**Placering:** `themes/moneyweb-base/`

**Filstruktur:**

```
moneyweb-base/
├── style.css              # Theme header
├── functions.php          # Setup, enqueue, nav-registrering
├── index.php              # Fallback
├── header.php             # HTML head, header, nav
├── footer.php             # Footer, wp_footer()
└── assets/
    ├── css/
    │   └── base.css       # Reset, CSS-variabler, container, spacing, typografi
    └── js/
        └── nav.js         # Mobilmenu toggle
```

**CSS-variabler i base.css:**

```css
:root {
  --mw-color-primary:   #000000;
  --mw-color-secondary: #ffffff;
  --mw-color-accent:    #000000;
  --mw-color-text:      #333333;
  --mw-color-bg:        #ffffff;

  --mw-space-xs:  0.5rem;
  --mw-space-sm:  1rem;
  --mw-space-md:  2rem;
  --mw-space-lg:  4rem;
  --mw-space-xl:  8rem;

  --mw-container: 1200px;
  --mw-padding:   1.5rem;

  --mw-font: system-ui, sans-serif;
}
```

Child Theme overskriver variabler i sin `style.css`.

---

## C. moneyweb-test-01

Minimalt Child Theme til at teste Core og API'et i fase 1.

**Placering:** `themes/moneyweb-test-01/`

**Filstruktur:**

```
moneyweb-test-01/
├── style.css              # Child theme header (Template: moneyweb-base)
├── functions.php          # Minimal — kun hvad der er nødvendigt
├── moneyweb-theme.json    # Simpelt manifest
└── front-page.php         # Renderer ACF-felter
```

**moneyweb-theme.json:**

```json
{
  "theme": "moneyweb-test-01",
  "theme_version": "1.0.0",
  "schema_version": 1,
  "global": [
    { "key": "company_name",  "type": "text", "required": true,  "label": "Virksomhedsnavn" },
    { "key": "company_phone", "type": "text", "required": true,  "label": "Telefon" }
  ],
  "pages": {
    "home": {
      "title": "Forside",
      "slug": "forside",
      "template": "front-page.php",
      "is_front_page": true,
      "label": "Forside",
      "fields": [
        { "key": "hero_heading", "type": "text",    "required": true,  "label": "Hero overskrift" },
        { "key": "hero_intro",   "type": "wysiwyg", "required": false, "label": "Introduktionstekst" },
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

---

## D. n8n testflow

1. Manuel trigger med statiske testdata (ingen AI i fase 1)
2. `GET /wp-json/moneyweb/v1/schema` — verificer response
3. `POST /wp-json/moneyweb/v1/site-data` med statisk payload
4. Log response inkl. warnings

---

## Acceptance criteria

Fase 1 er godkendt når:

- [ ] Core giver tydelig admin-notice hvis ACF Pro mangler (deaktiverer sig IKKE selv)
- [ ] Core aktiveres uden fejl på mwsite.dk med ACF Pro aktivt
- [ ] Core læser `moneyweb-theme.json` fra aktivt Child Theme
- [ ] ACF feltgrupper registreres automatisk med stabile, deterministiske keys
- [ ] Options Page "Moneyweb Indstillinger" oprettes og globale felter gemmes korrekt
- [ ] `GET /schema` returnerer korrekt JSON med gyldig API-key
- [ ] `GET /schema` returnerer 401 med ugyldig API-key
- [ ] `POST /site-data` finder eller opretter WordPress-sider fra manifest
- [ ] Sider får korrekt `_moneyweb_page_key` meta
- [ ] Side med `is_front_page: true` sættes som statisk forside via `show_on_front` og `page_on_front` — ikke via `_wp_page_template`
- [ ] Almindelige sider får `_wp_page_template` sat til deres template-filnavn
- [ ] `POST /site-data` gemmer feltværdier korrekt via ACF
- [ ] Image-felter importeres til media library og gemmes som attachment-ID
- [ ] `POST /site-data` returnerer 400 ved forkert theme
- [ ] `POST /site-data` returnerer 400 ved forkert schema_version
- [ ] `POST /site-data` returnerer 400 ved manglende obligatoriske felter
- [ ] Ukendte felter i payload returneres i `warnings`, gemmes ikke
- [ ] `moneyweb-base` aktiveres som Parent Theme uden PHP-fejl
- [ ] `moneyweb-test-01` renderer ACF-data korrekt i browseren
- [ ] n8n testflow sender data og Core gemmer det korrekt
- [ ] mw1.dk er urørt
