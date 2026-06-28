# Core fields — moneyweb-core

Faste, altid-på globale felter som Moneyweb Core registrerer på alle sites.
Sandhedskilde: [`plugins/moneyweb-core/includes/class-core-fields.php`](../plugins/moneyweb-core/includes/class-core-fields.php).

Disse keys er **reserverede** — et Child Theme må ikke definere et felt med samme key. Et manifest, der gør det, får `GET /schema` til at returnere HTTP 422 (`reserved_field_key`).

---

## Felter

| Key | Type | Required | Label |
|---|---|---|---|
| `company_name` | text | ✓ | Virksomhedsnavn |
| `company_phone` | text | ✓ | Telefon |
| `company_email` | text | ✓ | E-mail |
| `company_address` | text | | Adresse |
| `company_cvr` | text | | CVR-nummer |
| `logo` | image | | Logo |
| `facebook_url` | text | | Facebook-URL |
| `instagram_url` | text | | Instagram-URL |
| `linkedin_url` | text | | LinkedIn-URL |
| `opening_hours` | repeater | | Åbningstider |
| `maps_url` | text | | Google Maps-URL |

### `opening_hours` sub-felter

| Key | Type | Required |
|---|---|---|
| `day` | text | ✓ |
| `open` | text | |
| `close` | text | |
| `closed` | true_false | |
| `note` | text | |

---

## ACF-lagring

| Item | Værdi |
|---|---|
| Feltgruppe | `group_mw_core_global` |
| Field key-prefiks | `field_mw_core_{key}` |
| Sub-field key | `field_mw_core_{key}_{sub_key}` |
| Location | `options_page == moneyweb-settings` |
| Lagring | `update_field($field_key, $value, 'option')` |

Læs i template med feltnavnet (ikke key): `get_field('company_name', 'option')`.

---

## Automation-default

Alle Core-felter har default `automation.action = "copy_from_onboarding"` med `onboarding_key` lig med feltets key. n8n kan dermed direkte mappe onboardingdata til Core-felter.

Eksempel fra `/schema`:

```json
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
}
```

---

## Bevidst udeladt fra fase 1.1

Følgende skal håndteres senere — og ikke som "passive" ACF-felter, men gennem korrekt WordPress- eller plugin-integration:

- favicon (→ WordPress Site Icon)
- standard social sharing-billede (→ SEO plugin / WP custom)
- Google Analytics (→ Site Kit / dedikeret plugin)
- Google Tag Manager
- Meta Pixel
- Cookiebot eller anden CMP (→ Cookiebot-plugin)
- SEO
- privatlivspolitik (→ `Indstillinger → Privatliv` i WP core)

Disse skal samles i Moneywebs fælles kontrolpanel i en senere fase.

---

## Tilladte `automation.action`-værdier

Valideres af Core ved manifest-load. Ugyldig værdi → HTTP 422 `invalid_automation_action`.

```text
copy_from_onboarding
generate_text
find_image
generate_image
find_or_generate_image
select_color
use_default
manual
```
