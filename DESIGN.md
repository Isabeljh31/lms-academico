---
name: LearnUp
description: Academic LMS for Peru — a trusted registrar's warm admin desk beside a scholar's cool study room.
colors:
  sage-deep: "#3C7A75"
  sage-base: "#7FB3AE"
  sage-pale: "#ECF8F8"
  terracotta-deep: "#B2604A"
  terracotta-base: "#CB8A72"
  terracotta-pale: "#E6BEAE"
  sand-medium: "#E7D8C9"
  sand-light: "#EEE4E1"
  scholar-blue: "#2541B2"
  ocean-deep: "#1768AC"
  celeste-pale: "#EBF0FB"
  dusty-lavender: "#5B4E8A"
  success-green: "#3F6B4A"
  ink: "#1F2937"
  cloud-white: "#F5F7FA"
typography:
  body:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  title:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    lineHeight: 1.3
  label:
    fontFamily: "Figtree, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    letterSpacing: "0.04em"
rounded:
  sm: "6px"
  md: "8px"
  lg: "12px"
  xl: "16px"
  full: "9999px"
spacing:
  sm: "8px"
  md: "16px"
  lg: "24px"
components:
  button-primary-admin:
    backgroundColor: "{colors.sage-deep}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-primary-admin-hover:
    backgroundColor: "{colors.sage-deep}"
  button-primary-scholar:
    backgroundColor: "{colors.scholar-blue}"
    textColor: "#ffffff"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  card:
    backgroundColor: "#ffffff"
    rounded: "{rounded.lg}"
    padding: "16px"
  modal:
    backgroundColor: "#ffffff"
    rounded: "{rounded.xl}"
    padding: "20px"
---

# Design System: LearnUp

## Overview

**Creative North Star: "El Registro Confiable" (The Trusted Registrar)**

LearnUp reads like an institution's own record-keeping desk, not a generic SaaS admin panel. Every surface should feel like it belongs to a place where things are tracked precisely and nothing disappears quietly: the Supervisor's screens have the warm, grounded confidence of a registrar's office (sand, terracotta, deep sage), while the Estudiante and Profesor share a cooler, more scholarly blue — two rooms in the same building, never two different products stitched together.

The system is deliberately **role-split rather than single-primary**: Supervisor surfaces run on the sage/terracotta/sand palette, Estudiante and Profesor surfaces run on the scholar-blue/celeste palette. This is a confirmed product decision (see the Role-Split Palette rule below), not an inconsistency to fix.

Components are tactile and confident: buttons give real press feedback (`active:scale-[0.97]`), cards lift gently on hover (`hover:-translate-y-0.5` with a shadow step-up), nothing animates just to animate. The explicit anti-reference is the generic "made by AI" look — oversaturated gradients, decorative emoji, interchangeable stock icons, surfaces that are too smooth to feel like they belong to a real institution. LearnUp should feel plain-spoken and precise instead: Spanish-only copy, plain hyphens (never em dashes or middle dots), a restrained two-weight type scale, and color used with intent rather than decoration.

**Key Characteristics:**
- Two deliberate accent worlds (warm for Supervisor, cool for Estudiante/Profesor), never mixed on the same screen.
- Flat-by-default surfaces with a single soft `shadow-sm`, stepping up to `shadow-md`/`shadow-lg` only on hover or in overlays.
- Rounded-corner, border-first cards (a 1px neutral border before any shadow does the separating).
- Every interactive element gives tactile press/hover feedback; nothing is inert.
- Icons are a single hand-picked Heroicons-outline set (1.5px stroke) — never mixed icon families, never emoji.

## Colors

Two independent accent systems, each with its own neutral scale, chosen by the viewer's role rather than by page type.

### Primary
- **Deep Sage Teal** (`#3C7A75`): the Supervisor's primary accent — primary buttons, active/focused states, the admin icon-badge tint. Used sparingly, mostly on calls to action.
- **Scholar Persian Blue** (`#2541B2`): the Estudiante/Profesor primary accent, same role as Deep Sage Teal but for the other half of the app. The two never appear on the same screen.

### Secondary
- **Burnt Terracotta** (`#B2604A`): Supervisor's warning/highlight accent — temporary-password reveals, "first login pending" badges, archived-state notices.
- **Ocean Deep** (`#1768AC`): Estudiante/Profesor's secondary accent, used the way Burnt Terracotta is used on the warm side.

### Tertiary
- **Dusty Lavender** (`#5B4E8A`): reserved for the Profesor role pill and anything that needs to read as "the other teaching role" next to Scholar Blue's student pill.

### Neutral
- **Sand Light** (`#EEE4E1`) / **Sand Medium** (`#E7D8C9`): Supervisor-side backgrounds, borders, and hover states.
- **Pale Celeste** (`#EBF0FB`): Estudiante/Profesor-side backgrounds, borders, and hover states.
- **Pale Sage** (`#ECF8F8`) / **Soft Terracotta** (`#E6BEAE`): light tint backgrounds behind Supervisor icon badges and status chips.
- **Ink** (`#1F2937`): body text on the Estudiante/Profesor side. The Supervisor side uses warm stone grays (Tailwind's `stone` scale, swapped in globally for `gray`) for the equivalent role.
- **Cloud White** (`#F5F7FA`): Estudiante/Profesor page background. Supervisor pages sit on plain white with Sand borders doing the separating instead.
- **Success Green** (`#3F6B4A`): shared across both sides for "active"/"on track" states — the one color that isn't role-split, because status meaning should read the same everywhere.

### Named Rules
**The Role-Split Palette Rule.** Supervisor screens never use Scholar Blue/Ocean Deep/Celeste; Estudiante and Profesor screens never use Sage/Terracotta/Sand. If a shared component (like the header dropdown) needs to adapt, it switches its whole token set by role, it never blends the two.

**The One Warm Signal Rule.** Terracotta (either side's "secondary" accent) is reserved for things the user must notice once and act on — a temporary password, a pending first login, an archived state — never for routine decoration.

## Typography

**Body Font:** Figtree (with ui-sans-serif, system-ui fallback)
**Label Font:** Figtree, same family, heavier weight and tracked-out for section labels and badges.

**Character:** One typeface, doing all the work through weight and size rather than a second display face — keeps the "institutional record" feel instead of a marketing-site pairing.

### Hierarchy
- **Title** (700, 1.5rem–1.875rem responsive, 1.3 line-height): page headers (`<h1>` inside `x-gestion.encabezado`), paired with the role's icon badge.
- **Subtitle** (600, 1rem, 1.4 line-height): card titles, modal titles.
- **Body** (400, 1rem, 1.5 line-height): default paragraph and form text.
- **Detail** (400, 0.875rem/`text-sm`, 1.4 line-height): card metadata, help text under fields — the floor for anything meant to be read, not just glanced at.
- **Label** (600, 0.75rem/`text-xs`, uppercase, 0.04em tracking): section eyebrows ("DATOS PERSONALES"), status badges, table headers.

### Named Rules
**The No-Caption-Smaller-Than-12px Rule.** `text-xs` is the floor for anything a user is meant to read as a sentence (use it for badges/eyebrows only); card detail lines use `text-sm` so they stay comfortable at a glance.

## Layout

Page content sits in a centered container (`max-w-6xl` for dashboards/tables, `max-w-3xl` for single-column forms) with `px-4 sm:px-6 lg:px-8` side gutters and `py-8` top/bottom rhythm — consistent across every `x-gestion`-based screen regardless of role. Cards in a grid use `gap-6`; fields inside a form use `gap-4` (or `gap-3` for dense filter bars). Mobile collapses every grid to one column before `sm:`.

## Elevation & Depth

Flat by default. Cards and panels are separated by a 1px neutral border first, with only a `shadow-sm` resting shadow. Depth is a response to interaction, not a static layer: interactive cards lift to `shadow-md`/`shadow-lg` with a `-translate-y-0.5` nudge on hover, modals and the portada lightbox jump straight to `shadow-2xl` because they're meant to feel like they're above the page, not beside it.

### Named Rules
**The Depth-Is-a-Response Rule.** Nothing above `shadow-sm` appears at rest. A heavier shadow always means "this is reacting to you" (hover) or "this is floating above everything else" (modal/overlay), never decoration.

## Shapes

`rounded-lg` (8px, Tailwind default) is the workhorse for buttons, inputs, and cards. Icon badges and highlighted info boxes step up to `rounded-xl` (12px) to read as a distinct, slightly softer container. Modals use `rounded-2xl` (16px) on desktop and square off to `rounded-t-2xl` only, full-bleed from a bottom sheet on mobile. Badges, pills, and avatars are always `rounded-full`. Borders are consistently 1px in the role's neutral tone — never a heavier or colored border for emphasis; emphasis comes from the ring utility (`ring-2`) on focus/active states instead.

## Components

### Buttons
- **Shape:** `rounded-lg` (8px).
- **Primary:** role accent background (Deep Sage Teal for Supervisor, Scholar Blue for Estudiante/Profesor), white text, `shadow-sm` at rest.
- **Hover / Focus:** primary darkens ~10% and lifts (`hover:-translate-y-0.5`, `hover:shadow-md`); every variant presses down on click (`active:scale-[0.97]`) — this is load-bearing for the "tactile and confident" feel, never skip it on a new button variant.
- **Secondary / Ghost / Danger:** Secondary is a bordered white button in the role's neutral tone; Ghost drops the border and background entirely until hover; Danger is always red regardless of role (status meaning over brand).

### Cards / Containers
- **Corner Style:** `rounded-xl` (12px).
- **Background:** white (light mode), role-tinted dark surface in dark mode (never plain black/gray).
- **Shadow Strategy:** `shadow-sm` at rest; interactive cards (course cards, clickable rows) step up on hover per the Depth-Is-a-Response Rule.
- **Border:** 1px, role-neutral tone (`border-arena-medio` Supervisor / `border-celeste` Estudiante-Profesor).
- **Internal Padding:** `p-4` to `p-5` depending on density.

### Inputs / Fields
- **Style:** white background, 1px role-neutral border, `rounded-lg`, left-icon variant available (icon absolutely positioned, input gets `pl-9`).
- **Focus:** border switches to the role's primary accent plus a matching `focus:ring` glow — no color outside the role's own accent ever appears on a field.
- **Error:** border and ring switch to red, paired with an inline icon + message below the field, regardless of role.
- **Live validation:** fields that can auto-suggest or conflict (course code, email) validate on every keystroke/blur and surface the message immediately, not only on submit.

### Modals
- **Corner Style:** `rounded-2xl` desktop, bottom-sheet `rounded-t-2xl` on mobile.
- **Header:** optional icon badge (matches the page-header pattern) + title + one-line description, close button top-right.
- **Shadow:** `shadow-2xl`, dimmed page behind it (`bg-stone-900/50` overlay).

### Navigation
- **Dropdown:** role-tinted surface and ring (not the old hardcoded blue-only version), `scale`+`opacity` enter transition, closes on outside click or Escape.
- **Badges/Pills:** `rounded-full`, role-tinted background at ~10-15% opacity with the matching deep tone as text — this is also how the Estudiante/Profesor role pills stay distinguishable (Scholar Blue vs. Dusty Lavender) without needing icons.

## Do's and Don'ts

### Do:
- **Do** keep each screen entirely inside its role's palette — check which `esAdmin`/role flag a shared component branches on before adding a color.
- **Do** give every clickable element a press state (`active:scale-[0.97]`) and every hoverable card a lift, even on small/secondary buttons.
- **Do** use plain hyphens (`-`) for any separator in UI copy — never an em dash or middle dot.
- **Do** reserve Terracotta/Ocean-Deep (the "secondary" accent) for things the user must notice once, not for routine UI chrome.

### Don't:
- **Don't** mix Sage/Terracotta/Sand with Scholar-Blue/Ocean-Deep/Celeste on the same screen, even in a shared component.
- **Don't** add a shadow heavier than `shadow-sm` to anything at rest; depth is earned by hover or overlay status only.
- **Don't** reach for gradients, emoji, or generic stock icon packs — this is the explicit anti-reference ("looks AI-generated"). Every icon comes from the project's single hand-picked Heroicons-outline set.
- **Don't** drop below `text-sm` for anything meant to be read as a sentence; `text-xs` is for badges and eyebrows only.
