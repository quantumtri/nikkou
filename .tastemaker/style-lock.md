# Style lock: Nikkou Web App (internal sales + admin app)

Source: studied from https://insurance-nikkou-web.quantumtri.com/ on 2026-11-11 (computed styles read from the live page).
Supersedes the "flat, square corners" rule in spec section 9 at the owner's request (change request, 2026-11-11).

## Design read
App shell + transactional forms. Sales on phones in the field, admin on desktop. Calm, warm, product-site polish
without marketing theatrics. Density: medium. Motion: low. Variance: low (it is a tool).

## Type
- Family: Outfit (self-hosted through the Vite fonts plugin), weights 400 / 500 / 600 / 700. One family only.
- Page title 28px/600, -0.01em. Section title 17px/600. Body 15px/400. Label 13px/500. Caption 12px.
- Newsreader italic (the marketing accent) is NOT used in the app.

## Color tokens (same brand palette as the reference)
red #E83828, deep #C0261B, darkest #7A1D14, coral #F49C94, rose #F9CDC9, sky #86B3E0, sky-light #DCE9F6,
navy #0B2540, ink #1A1A1A, body #4A4543, grey #727171, mist #EFEFEF, paper #FAF8F7, line #E6E2E0,
field-border #8A8482.

## Color contract (WCAG ratios, computed)
Text-safe (>= 4.5:1): ink on white 17.4, ink on paper 16.4, body on white 9.4, body on paper 8.9,
grey on white 4.9, grey on paper 4.6, white on deep 5.9, deep on white 5.9, deep on paper 5.6,
darkest on rose 7.3, navy on sky-light 12.6, white on darkest 10.4.
Large text / UI only (>= 3:1): white on red 4.2, deep on rose 4.1, field-border on white 3.7.
Decorative only: line on white 1.3, coral on white 2.1.
Rules: buttons and small text use deep, never red. Text on rose is darkest. Text on sky-light is navy.
Input borders use field-border; card borders may use line.

## Shape
Buttons and chips: pill (999px). Inputs: 14px. Cards and tiles: 20px. Large panels: 28px.
No shadows; separation comes from paper/white surfaces and 1px line borders.
Printed label previews stay square (they depict physical labels).

## Density & spacing
4px base. Card padding 16-20px (phone), 24px (desktop). Gap between cards >= card padding.
Section gap 28px on phone forms. Touch targets >= 44px; primary buttons 52px.

## Motion (app shell track)
CSS only, no animation library. 150ms ease-out on color/border/transform; pressed state scale .98.
No page-enter fade: content must never start hidden (a background tab would show a blank form).
All motion is disabled under prefers-reduced-motion.

## Icons
Lucide outline icons, inlined as SVG, 1.75 stroke, currentColor. Bottom navigation and scan button only.

## Assets
Logo: existing Nikkou wordmark and symbol from the brand guideline folder, unchanged.
No photography or illustration in the app.
