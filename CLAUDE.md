# CLAUDE.md — Moneyweb WordPress Platform

## Hvad dette er

Moneyweb.ai leverer hjemmesider til små virksomheder via WordPress Multisite.
Dette repository indeholder den nye tema- og plugin-arkitektur der erstatter Oxygen Builder.

Målet er den mindste stabile løsning der virker:
- `moneyweb-base` — Parent Theme med fælles fundament
- `moneyweb-core` — lille plugin der læser Child Theme-skema, registrerer ACF-felter og håndterer API
- Child Themes — konkrete designs med egne templates, CSS og `moneyweb-theme.json`
- n8n henter skemaet og sender indhold som JSON

## Ansvarsfordeling

| Komponent | Ansvar |
|---|---|
| **Child Theme** | Design, templates, CSS, JS, rendering af ACF-data, `moneyweb-theme.json` |
| **moneyweb-core** | Læser manifest, registrerer ACF-felter, schema endpoint, site-data endpoint, validering, gem via ACF |
| **moneyweb-base** | Theme setup, header/footer, navigation, container/spacing, CSS-reset, basisvariabler, helpers |
| **n8n** | Opretter subsite, aktiverer theme, henter schema, genererer indhold, sender JSON |
| **ACF Pro** | Feltlagring — ingen anden komponent skriver direkte til databasen |

## Regler der aldrig må brydes

1. **mw1.dk røres ikke** — al udvikling og test sker på mwsite.dk
2. **n8n skriver ikke direkte til databasen** — kun via moneyweb-core API
3. **Post Types registreres ikke i themes**
4. **Eksisterende ACF-feltnavne på mw1.dk omdøbes ikke** uden godkendt migrationsplan
5. **Ingen hurtige løsninger** — kode skal være færdig og operationel
6. **WordPress sikkerhedsstandarder** — sanitering, escaping, capability checks

## Hvad der ikke bygges nu

- Kundekontrolpanel
- Booking eller prisberegnere
- HMAC-signering (simpel API-key er tilstrækkeligt nu)
- Rollback-system
- Migrationssystem til eksisterende Oxygen-sites
- Legacy ACF-feltmapping (bruges senere ved migration)

## Dokumentation

Læs altid relevante docs-filer inden du skriver kode:

- `docs/architecture.md` — arkitektur og dataflow
- `docs/api-contract.md` — schema- og site-data-endpoints
- `docs/theme-manifest.md` — format for moneyweb-theme.json
- `docs/phase-1.md` — hvad der bygges og acceptance criteria

## Fremtidige Child Themes

Konkrete designs laves separat i Claude Design og afleveres som designreference, HTML, CSS, React-komponenter eller billeder.

Når et design skal konverteres til et WordPress Child Theme skal Claude Code:
- Bevare designets visuelle udtryk så præcist som muligt
- Oprette WordPress-templates, CSS og JavaScript i Child Theme
- Identificere designets sider, sektioner og indholdsbehov
- Oprette `moneyweb-theme.json` ud fra de felter designet faktisk kræver
- Bruge `moneyweb-base` som Parent Theme
- Bruge `moneyweb-core` til ACF og API
- Ikke flytte designspecifik kode ind i moneyweb-core eller moneyweb-base

## Spørg Victor før du

- Ændrer API-kontrakten
- Tilføjer nye afhængigheder
- Rører mw1.dk
- Bygger noget der ikke er i scope for den aktuelle fase

## Rettelser der tilsidesætter gamle linjer i øvrige docs

- Ingen Custom Post Types i fase 1
- CPT'er må aldrig registreres i Child Themes
- Core deaktiverer sig ikke selv hvis ACF mangler — vis admin notice og returnér 503
- Template-værdien hedder `front-page.php`
- For `is_front_page: true` — sæt `show_on_front` og `page_on_front`, men IKKE `_wp_page_template`
