# SupportAI Brand Guide

**Tagline:** Smarter Support. Happier Customers.
**Personality:** trustworthy, intelligent, modern, professional, approachable.

## Colors
| Role | Name | Hex |
|---|---|---|
| Brand | Primary Blue | `#2563EB` |
| Accent (AI) | AI Purple | `#7C3AED` |
| Success | Success Teal | `#14B8A6` |
| Text | Navy | `#0F172A` |
| Text secondary | Slate | `#64748B` |
| Background | Cloud | `#F8FAFC` |
| Dark-mode icon pair | Blue 500 / Violet 500 | `#3B82F6` / `#8B5CF6` |
| Dark-mode "AI" text | Sky | `#60A5FA` |

Brand gradient: 135°, `#2563EB` → `#7C3AED`.

## Typography
Inter (Google Fonts, free for commercial use). Headings Bold/ExtraBold, UI and body Regular/Medium.
The logo wordmark is outlined in the SVGs, so Inter is not required to display the logo.

## Logo files (`svg/`, `png/`)
| Use | File |
|---|---|
| Website header, dashboard, docs (light bg) | `logo-horizontal.svg` / `.png` |
| Dark backgrounds | `logo-horizontal-dark.svg` / `.png` |
| One-colour print, stamps, watermarks | `logo-horizontal-mono-navy`, `-mono-white` |
| Landing hero, email footer | `logo-horizontal-tagline` |
| Symbol only (chat widget launcher, avatar) | `symbol`, `symbol-dark`, `symbol-mono-*` |
| App icon | `app-icon.svg`, `app-icon-512/1024.png` |
| PWA / Android maskable | `app-icon-maskable-512.png` |
| iOS | `apple-touch-icon-180.png` |
| Favicons | `favicon.svg`, `favicon.ico` (16/32/48), `favicon-16/32/48/64.png` |

## Spacing
- **Clear space:** keep at least the height of the chat-bubble's eye ("screen") clear on all sides (about 25% of the symbol height).
- **Minimum size:** horizontal logo 120 px wide; symbol 16 px (use `favicon-16.svg` below 24 px, it drops the sparkle).

## Do
- Use the supplied files, not a retyped wordmark.
- Use the dark version on navy/dark surfaces, the colour version on white/Cloud.
- Keep contrast high; use mono versions on busy or photographic backgrounds.

## Don't
- Stretch, rotate or recolour the logo; add shadows or effects.
- Put the colour logo on a blue/purple background (use mono-white).
- Replace Inter in the wordmark.

## HTML snippet
```html
<link rel="icon" href="/favicon.ico" sizes="48x48">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon-180.png">
<link rel="manifest" href="/site.webmanifest">
```
`site.webmanifest` icons: `icon-192.png`, `app-icon-512.png`, `app-icon-maskable-512.png` (purpose `maskable`).

## Before launch
Check trademark and domain availability for "SupportAI" in your target markets; the name is generic and several companies already use similar names.
