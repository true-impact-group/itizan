# Itizan — design direction (approved)

Warm, modern, calm. The feeling: "they understand me and I can start with confidence".

| Token | Value | Use |
|---|---|---|
| ivory | `#FAF8F4` | page background |
| petrol | `#16645B` (dark `#0F4A43`) | primary buttons, links, key elements |
| mint | `#E5F2EC` | calm section backgrounds (`petrol-soft`) |
| peach | `#F3C6AD` (soft `#FBE9DE`) | small warm touches, final call to action |
| ink | `#20332F` (soft `#4B5F5A`) | text |

Font: IBM Plex Sans Arabic, self-hosted, weights 400/500/600/700. Pill buttons (`rounded-full`), soft 24–32 px cards.

## Glass (`.glass`)
Only on **floating** elements: sticky header, hero chips, the mobile "start" bar, and the client's "next step" card.
Never behind forms, prices, package comparison, clinical data or long text.
Safeguards (all in `resources/css/app.css`): ≥ 82 % opaque, solid fallback without `backdrop-filter`, solid under
`prefers-reduced-transparency` and forced-colors, blur ≤ 12 px.

## Motion
Short fade/slide-in for below-the-fold blocks (`.rv`, hidden only while JS runs), small hover lift. Nothing loops, no auto sliders;
`prefers-reduced-motion` disables all of it.

## Images
The hero uses `public/images/hero.jpg` when present (licensed photo of a local person in an everyday positive moment; no scales
or measuring tapes); otherwise a neutral illustration stand-in (`components/hero-art.blade.php`). Team cards currently show
initials; add practitioner photos only with their consent. Do not show testimonials, results or trust numbers unless documented.

## Mobile
Sticky bottom bar "ابدأ باستشارة مجانية" for guests (the WhatsApp button lifts above it), large targets, short copy.

## Client dashboard
Answers "what is my next step?" first (glass card), encouraging journey progress (steps done of total), appointment, team contact;
weight is one block among several, never the only number.

Static concept boards used for approval live in `docs/design/` (`board.html`).
