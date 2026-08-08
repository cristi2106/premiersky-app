---
name: PremierSky Admin
description: Internal charter-brokerage ops tool — quotes, contracts, fleet, and clients run from one flight deck.
colors:
  runway-blue-50: "#eff6ff"
  runway-blue-500: "#3b82f6"
  runway-blue-600: "#2563eb"
  runway-blue-700: "#1d4ed8"
  night-ops-black: "#030712"
  ink: "#111827"
  ink-deep: "#1f2937"
  paper: "#f9fafb"
  surface: "#ffffff"
  border: "#e5e7eb"
  text-muted: "#4b5563"
  text-faint: "#6b7280"
  text-quiet: "#9ca3af"
  success: "#dcfce7"
  success-text: "#15803d"
  warning: "#fef9c3"
  warning-text: "#a16207"
  danger: "#dc2626"
  danger-bg: "#fee2e2"
  danger-text: "#b91c1c"
  emblem-black: "#000000"
  emblem-gold: "#d4af37"
typography:
  body:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.25rem"
  title:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: "1.75rem"
    letterSpacing: "-0.01em"
  label:
    fontFamily: "Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    letterSpacing: "0.05em"
rounded:
  sm: "6px"
  md: "8px"
  lg: "12px"
  full: "9999px"
spacing:
  xs: "8px"
  sm: "12px"
  md: "16px"
  lg: "24px"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-primary-hover:
    backgroundColor: "#1f2937"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "#374151"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.surface}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  badge:
    rounded: "{rounded.full}"
    padding: "2px 10px"
    typography: "{typography.label}"
  card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
---

# Design System: PremierSky Admin

## Overview

**Creative North Star: "The Flight Deck"**

PremierSky Admin is an instrument panel, not a showroom. Every screen exists so one of a small, trusted team of brokers can do one job fast: read a quote, compare offers, price a contract, check a tail. The design has no persuasion to do and no audience to impress — it earns its keep in repetition, at a desk, many times a day, so it stays out of the way of the task.

The system is built almost entirely from two ingredients: near-black neutrals that carry structure and authority (navigation, primary actions, headings), and a single Runway Blue accent spent sparingly on the things that are genuinely interactive — active nav state, links, focus rings. Surfaces are flat by default; shadow is reserved for things that are temporarily floating above the page (a modal, a dropdown, a search menu), never for something that just sits there. Nothing about the interface itself is decorative — the one deliberately expressive mark is the black-and-gold aviation emblem in the nav, held apart from the functional palette as a fixed brand signature rather than a UI color.

**Key Characteristics:**
- Flat, bordered surfaces; shadow signals "floating," not "important."
- One accent color (Runway Blue), spent only on interactive/active state.
- Near-black (Night Ops Black / Ink) carries visual weight instead of the accent.
- Dual layout per data view: a real `<table>` on desktop, stacked swipeable cards on mobile — never a squeezed table.
- Small, consistent type scale dominated by 14px body text; nothing shouts.

## Colors

A restrained, mostly-neutral palette: near-black and gray do the structural work, Runway Blue is spent only where something is clickable or focused, and semantic tints (success/warning/danger) are reserved strictly for status.

### Primary
- **Runway Blue** (#2563eb / `accent-600`): the one accent. Active sidebar/bottom-nav item background, text links, the selected state ring on a quote offer card.
- **Runway Blue Focus** (#3b82f6 / `accent-500`): every focus ring and focus border across inputs, selects, and buttons — the single, consistent "you're interacting with this" signal.
- **Runway Blue Hover** (#1d4ed8 / `accent-700`): hover state for text links and active-nav-adjacent text.
- **Runway Blue Tint** (#eff6ff / `accent-50`): selected-row background in search menus (`SearchableSelect`); the info badge's tint pairs with `accent-700` text.

### Neutral
- **Night Ops Black** (#030712 / `gray-950`): sidebar and mobile nav-drawer background. The one large dark surface in the system.
- **Ink** (#111827 / `gray-900`): primary button fill, page headings, user-avatar circle, table cell primary text.
- **Ink Deep** (#1f2937 / `gray-800`): primary button hover fill.
- **Paper** (#f9fafb / `gray-50`): the app's base background, behind every card.
- **Surface** (#ffffff): card, table, modal, and dropdown backgrounds.
- **Border** (#e5e7eb / `gray-200`): card borders, table dividers, top bar border, dropdown/modal borders.
- **Text Muted** (#4b5563 / `gray-600`): secondary body text, table cell values, nav item resting label.
- **Text Faint** (#6b7280 / `gray-500`): field labels, `dt` terms, helper copy.
- **Text Quiet** (#9ca3af / `gray-400`): placeholders, disabled icon color, resting bottom-nav icon.

### Semantic (status only)
- **Success** (bg #dcfce7 / text #15803d): "in fleet" and other confirmed-state badges.
- **Warning** (bg #fef9c3 / text #a16207): pending/attention-needed status.
- **Danger** (#dc2626 fill / #fee2e2 bg / #b91c1c text): destructive actions and error text.

### Brand mark only (not a UI color)
- **Emblem Black** (#000000) and **Emblem Gold** (#d4af37): the fixed aviation-wing mark used only inside `ApplicationLogo`. Never pulled into buttons, links, or status color — the emblem is a signature, the interface runs on Ink and Runway Blue.

### Named Rules
**The One Accent Rule.** Runway Blue exists to answer "is this interactive, active, or focused?" — nothing else. Weight and hierarchy come from Ink and type size, never from adding a second accent.

## Typography

**Body/UI Font:** Inter (with -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, ui-sans-serif, system-ui, sans-serif fallback)

**Character:** A single, workmanlike sans stack carries the entire interface — no display face, no serif contrast. The scale is compressed and heavily weighted toward one size (14px), so hierarchy comes from weight and color, not size jumps.

### Hierarchy
- **Title** (font-semibold/600, 1.25rem/20px, tracking-tight): the top-bar page title (`AdminLayout`'s `title` prop) — one per screen.
- **Subtitle** (font-medium/500, 1.125rem/18px): modal headings ("Delete client?"), confirmation dialogs.
- **Label** (font-medium/500, 0.75rem/12px, tracking-wide, uppercase on table headers): form field labels, table column headers.
- **Body** (font-normal/400–font-medium/500, 0.875rem/14px): the dominant size — table cells, card copy, nav items, buttons, badges, inputs. Nearly everything the user reads.
- **Micro** (0.75rem/12px): timestamps ("Saving…" / "Saved"), badge text, helper notes under a field.

### Named Rules
**The One Size Rule.** 14px (`text-sm`) is body text everywhere — table, card, form, nav. Reach for weight (`font-medium`/`font-semibold`) or color (Ink vs. Text Muted) to create emphasis before reaching for a bigger size.

## Layout

Single max-width column: `max-w-7xl` centered content area, `px-4`/`sm:px-6`/`lg:px-8` responsive gutters. Desktop uses a fixed 256px (`w-64`) dark sidebar (`lg:pl-64` on the main column); below `lg`, the sidebar collapses into a slide-in drawer plus a persistent bottom tab bar (`BottomNav`) with four primary destinations and a fifth set-apart refresh action.

Every data-heavy screen (Clients, Contracts, Tails, Airports…) renders in one of two literal layouts depending on viewport — never a single cramped responsive table:
- **`sm:` and up:** a bordered `<table>` inside a `.card`, header row `bg-gray-50` with uppercase 12px labels, body rows `divide-y` with a `hover:bg-gray-50` row highlight.
- **below `sm`:** stacked `SwipeableListItem` cards, each a `dt`/`dd` definition list, swipe-left-to-reveal delete.

Spacing runs on a 4px base rhythm expressed mostly in multiples of 4: `gap-2`/`gap-3`/`gap-4` (8/12/16px) between inline elements, `p-4`/`p-6` (16/24px) card interior padding, `py-2`/`px-4` (8/16px) as the standard control padding.

## Elevation & Depth

Flat by default, shadow used only for things temporarily above the page. Persistent surfaces (cards, the sidebar, the top bar) carry a 1px border and no shadow — depth is not the point, the border is enough to separate a surface from `Paper` beneath it. Shadow appears exclusively on floating/overlay UI: form controls get a whisper of it to read as "editable," and true overlays (dropdown menus, the modal) get progressively more of it the further they float above the page.

### Shadow Vocabulary
- **Field** (`shadow-sm`, Tailwind default `0 1px 2px 0 rgb(0 0 0 / 0.05)`): every text input, select, and searchable-select field — just enough to read as a control, not a surface.
- **Menu** (`shadow-md`, Tailwind default `0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)`): dropdown menu, searchable-select results list.
- **Overlay** (`shadow-lg`, Tailwind default `0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)`): the modal panel — the one thing genuinely interrupting the page.

### Named Rules
**The Floating-Only Rule.** If it sits in the page's normal flow (a card, the sidebar, the top bar), it gets a border and no shadow. If it floats above the page (menu, modal) or is a live control (input), it gets shadow scaled to how far above the page it sits.

## Shapes

Two radius steps do all the work, plus full pills for status/avatars. `rounded-md` (8px, Tailwind `rounded-lg` class) is the default for anything clickable or editable — buttons, inputs, nav items, table action buttons. `rounded-lg` (12px, Tailwind `rounded-xl` class) marks the larger containing surfaces — cards and the modal panel. `rounded-full` is reserved for pills (badges) and circles (the user avatar, the searchable-select clear button). Borders are always 1px, solid, `Border` gray — no double borders, no dashed states.

## Components

### Buttons
- **Shape:** 8px radius (`rounded-lg`), 1px border (transparent on filled variants, `Border` gray on Secondary).
- **Primary:** Ink (#111827) fill, white text, `px-4 py-2` (16px/8px), `font-medium`, hover darkens to Ink Deep (#1f2937), active darkens further to `gray-950`. Note this means the *primary* action color is the neutral Ink, not Runway Blue — the accent is reserved for links/active-nav, not buttons.
- **Secondary:** white fill, `Border` gray border, `gray-700` text, hover fills `gray-50`.
- **Danger:** `#dc2626` fill, white text, hover `#ef4444`, active `#b91c1c` — deletion and other irreversible actions only.
- **Focus:** every variant gets a 2px Runway Blue Focus ring with 2px offset (`focus:ring-2 focus:ring-accent-500 focus:ring-offset-2`) — the one focus treatment used everywhere, including on Danger buttons (which ring red instead).

### Badges
- **Style:** `rounded-full` pill, `px-2.5 py-0.5`, 12px `font-medium` text.
- **Variants:** success (green), warning (yellow), danger (red), info (Runway Blue Tint bg / Runway Blue Hover text), neutral (gray). Status only — never used as a generic label or count chip.

### Cards / Containers
- **Corner Style:** 12px radius (`rounded-xl`).
- **Background:** Surface (white) on Paper (gray-50) page background.
- **Shadow Strategy:** none — see Elevation & Depth. Separation comes entirely from the 1px `Border` gray border.
- **Internal Padding:** `p-6` (24px) default, `p-4` (16px) on denser cards like `QuoteOfferCard`.

### Inputs / Fields
- **Style:** `Border` gray stroke, 8px radius, `shadow-sm`, `px-3 py-2`, 14px text, gray-400 placeholder.
- **Focus:** border and ring both shift to Runway Blue Focus (`focus:border-accent-500 focus:ring-accent-500`) — border and ring change together, never one without the other.
- **Error:** a `text-sm text-red-600` message beneath the field (`InputError`); the field itself does not currently change color on error.

### Navigation
- **Sidebar (desktop ≥lg):** fixed 256px, Night Ops Black background, white brand wordmark, nav items `text-gray-400` at rest → `bg-accent-600 text-white` when active, `hover:bg-white/5 hover:text-white` otherwise. 8px item radius, `gap-3` icon-to-label spacing.
- **Bottom tab bar (mobile <md):** fixed, white, top-bordered, 4 equal icon tabs (`text-gray-400` resting → `text-accent-600` active) plus a visually set-apart 5th refresh action (`gray-50` bg, left border) — an action, deliberately not a peer destination.
- **Top bar:** white, bottom-bordered, sticky, houses the page `Title` and the user menu; the user avatar is a solid Ink circle with the first initial in white.

### Quote Offer Card (signature component)
The comparison unit of the Quotes module: a bordered card that turns into a 2px Runway Blue ring when the offer is selected for the client PDF — the *only* place in the system a full ring replaces a border, marking a deliberate, stateful choice rather than passive hover/focus. Operator name and price sit in Ink at top; a green "✓ in fleet" badge appears only when the offer's tail is a known aircraft. A nested `bg-gray-50` panel (no border, no shadow — a tint is enough at this depth) holds the flight itinerary. Commission inputs autosave with a debounce and report their own state inline ("Saving…" / "Saved") in 12px Text Quiet — status text never a toast or banner.

## Do's and Don'ts

### Do:
- **Do** keep Runway Blue exclusive to interactive/active/focus state — links, active nav, focus rings, the selected-offer ring.
- **Do** use Ink (#111827), not the accent, as the default primary-button fill; the accent is a signal color, not a brand color.
- **Do** give every card and persistent surface a border instead of a shadow; reserve shadow for menus, modals, and live form controls (The Floating-Only Rule).
- **Do** ship both a `<table>` (`sm:` and up) and a stacked-card view (below `sm`) for any tabular data screen — never rely on horizontal scroll or a squeezed table on mobile.
- **Do** keep body copy at 14px (`text-sm`) and reach for weight/color before size when something needs emphasis (The One Size Rule).

### Don't:
- **Don't** pull Emblem Black/Emblem Gold into UI chrome — they belong to `ApplicationLogo` only, never to a button, badge, or status color.
- **Don't** add a second accent color; new statuses or emphasis states extend the existing success/warning/danger/info/neutral badge set instead.
- **Don't** put a shadow on a surface that sits in normal page flow (cards, sidebar, top bar) — if it needs to stand out, use the border or Paper/Surface contrast instead.
- **Don't** introduce a new radius step; every clickable/editable element is 8px, every containing surface is 12px, pills are full-round.
