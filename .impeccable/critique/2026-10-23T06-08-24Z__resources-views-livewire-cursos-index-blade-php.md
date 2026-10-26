---
target: pantalla de cursos (index)
total_score: 24
max_score: 36
na_heuristics: 5
p0_count: 0
p1_count: 2
target_identity: "file:D:\\LMS\\lms-academico\\resources\\views\\livewire\\cursos\\index.blade.php"
target_fingerprint: "sha256:16aa3b6112376f810b5f98a57455b5f0f2abf823ca92fdf83de13e77eabe21bf"
target_path: "D:\\LMS\\lms-academico\\resources\\views\\livewire\\cursos\\index.blade.php"
timestamp: 2026-10-23T06-08-24Z
slug: resources-views-livewire-cursos-index-blade-php
---
Method: dual-agent (A: design review sub-agent · B: detector-scan sub-agent)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2 | Search/filter (`wire:model.live.debounce`) has no `wire:loading` indicator anywhere — the round-trip is invisible. |
| 2 | Match System / Real World | 3 | Same `$curso->modulos->count()` is called "unidades" for Estudiante, "módulos" for Profesor/Supervisor — same concept, two words. |
| 3 | User Control and Freedom | 3 | No "clear filters" affordance when search/filter returns zero results. |
| 4 | Consistency and Standards | 2 | Search input is a labeled `x-gestion.campo` in the admin branch, a bare label-less `<input>` in the Estudiante branch, for the identical control. |
| 5 | Error Prevention | n/a | Read-only list screen, no destructive/submittable action exists here. |
| 6 | Recognition Rather Than Recall | 3 | Cards surface enough info without a click-through, but active filter state is never echoed back ("3 resultados para…"). |
| 7 | Flexibility and Efficiency | 2 | Fixed `orderBy('nombre')`, hardcoded `paginate(9)`, no sort/page-size control — no way to surface "current" courses first. |
| 8 | Aesthetic and Minimalist Design | 3 | Clean grid, but admin cards pack up to 7 data points into one `p-4` card with no sub-grouping. |
| 9 | Error Recovery | 2 | `x-gestion.alertas` (flash messages) only renders on the Profesor/Supervisor branch — the Estudiante branch has no flash rendering at all. |
| 10 | Help and Documentation | 4 | Role-aware empty-state copy on the admin branch functions as sufficient inline help. |
| **Total** | | **24/36** | **Acceptable (66.7%)** |

## Design Specificity Verdict

**LLM assessment:** Mixed. Real LearnUp-specific authorship exists at the architecture level — the dual role-branch split, the `badgeEstado` vocabulary tied to RF-22, the co-teaching (`profesoresAdicionales`) model. But the card body leans on the *least* distinctive data available: neither branch shows `modalidad` (presencial/remoto/virtual) or capacity (`cupo_maximo`/`estaLleno()`) — two of the most Peru/LearnUp-specific facts about a course per PRODUCT.md. Instead both branches fall back to a generic initials-monogram placeholder derived from the course *name*, even though the admin branch already prints the real, distinctive `codigo` (e.g. `MAT1-26-01`) as plain text elsewhere on the same card. The screen is authored at the skeleton level and generic at the content level.

**Deterministic scan:** 1 advisory finding, 0 blocking. `design-system-font-size` on line 115 (admin branch): the status-badge span uses the arbitrary Tailwind value `text-[10px]`, below DESIGN.md's documented type floor (`text-xs`/12px is the smallest size on the ramp, reserved for badges/eyebrows — this badge undercuts even that). No false positives; the Blade/Livewire syntax on the page didn't confuse the regex scan. This is a finding the design review missed — exactly the kind of literal, measurable drift a detector catches that a read-through doesn't.

**Visual overlays:** Not available. No browser automation tool exists in this environment, so neither assessment could render or screenshot the live page; both worked from source only. Take the hierarchy/spacing judgments below as code-level inference, not confirmed pixels.

## Overall Impression

The bones are right — role-based palette split, status vocabulary, elevation rules are all followed correctly where the code is shared (`x-gestion.*`). The damage is in the parts that *aren't* shared: the Estudiante branch was hand-rolled instead of routed through the same components, and it shows — a stray admin-colored button, no flash messages, an unlabeled search input, a worse empty state for the exact persona (first-time student) who most needs reassurance. The single biggest opportunity is structural, not cosmetic: put Estudiante on the same component foundation Profesor/Supervisor already use, and most of these issues disappear at once instead of needing five separate patches.

## What's Working

1. **Elevation discipline is exact.** Both branches rest at `shadow-sm` and only escalate (`shadow-lg`/`shadow-md` + `-translate-y-0.5`) on hover — a literal match to DESIGN.md's Depth-Is-a-Response Rule, with nothing heavier than `shadow-sm` at rest anywhere in the file.
2. **`$badgeEstado` is a clean single source of truth**, reused identically by both branches and correctly left un-role-tinted (green/stone/amber/red) — status meaning reads the same no matter who's looking, exactly as DESIGN.md intends.
3. **The admin empty state is genuinely situation-aware**: its copy branches on `$puedeCrear` to give the Supervisor a call-to-action and the Profesor an explanation — real empathy for the "no self-service" constraint baked into the copy itself.

## Priority Issues

**[P1] Misleading empty-state copy for first-time Estudiantes**
- **Why it matters:** A brand-new student with zero enrollments (a normal first state in a no-self-registration product) is told *"No hay cursos que coincidan con tu búsqueda"* even when they never searched — false and confusing for the persona most likely to be confused.
- **Fix:** Branch the copy on whether a search/filter is actually active, mirroring the admin branch's `$puedeCrear`-aware `x-gestion.vacio` pattern.
- **Suggested command:** `/impeccable clarify`

**[P1] Role-Split Palette Rule violation inside the Estudiante branch**
- **Why it matters:** The "+ Nuevo curso" button (line 42) is hardcoded `bg-menta-oscuro` — DESIGN.md's Supervisor-only Deep Sage Teal — inside a block reachable only by `hasRole('estudiante')`. Masked today because `CursoPolicy::crear` blocks students from creating courses, but it's a confirmed rule violation one policy change away from becoming visible.
- **Fix:** Delete the button (currently unreachable/dead) or recolor to `azul-oscuro` if it's ever made reachable.
- **Suggested command:** `/impeccable audit`

**[P2] No flash-message rendering on the Estudiante branch**
- **Why it matters:** `x-gestion.alertas` only renders on the Profesor/Supervisor branch. Any session flash ever routed back to this page for a student silently never displays.
- **Fix:** Add an equivalent flash-render block to the `$paletaFria` branch.
- **Suggested command:** `/impeccable harden`

**[P2] The LMS's own distinguishing data is absent from the card**
- **Why it matters:** Neither branch shows `modalidad` or a matriculados/cupo ratio, despite both being core, Peru-specific facts per PRODUCT.md — the main reason the screen reads as generic rather than LearnUp's own (see Design Specificity Verdict).
- **Fix:** Add a modalidad chip and a "18/20 matriculados" style line to the card metadata.
- **Suggested command:** `/impeccable adapt`

**[P3] Terminology drift, a missing null-safe fallback, and a sub-12px badge**
- **Why it matters:** "unidades" vs. "módulos" for the same count, a dangling `"Prof. "` when an Estudiante's course has no profesor (admin branch already guards this with `?: 'sin asignar'`), and the detector-flagged `text-[10px]` badge below the documented type floor — three small signs the two branches and this one detail were never reconciled against the design system.
- **Fix:** Pick one term for module count across roles, add the same null-safe fallback to the Estudiante branch, bump the badge to `text-xs`.
- **Suggested command:** `/impeccable clarify`

## Persona Red Flags

**Jordan (First-Timer, Estudiante):** Hits the P1 empty-state issue directly — their very first visit after enrollment (or before it) shows a sentence implying a failed search they never ran. Worst possible first impression for the persona most likely to take it literally.

**Sam (Accessibility):** The Estudiante search input has no `<label>`, relying only on a `placeholder` (disappears on focus, unreliable for assistive tech) — compounded by `x-icono` exposing every decorative calendar/book/users icon to screen readers with no `aria-hidden`, several per card.

**Alex (Power User, Profesor/Supervisor):** No sort control beyond a fixed `orderBy('nombre')`, no page-size control (`paginate(9)` hardcoded), no way to see "what's about to start" or "what needs publishing" without scanning badges page by page — a real daily-efficiency gap for someone whose primary workspace this is.

## Minor Observations

- Page `<title>` (`Index.php` line 63) uses an em dash (`'Cursos — LearnUp'`) — the project's own explicit rule is plain hyphens only, no em dashes or middle dots.
- Lines 54-64 contain a structurally unreachable `@if(!$paletaFria)` nested inside the outer `@if($paletaFria)` branch — harmless today, confusing for the next editor.
- Card image-header heights differ between branches (`h-32` student vs. `h-28` admin) with no apparent reason.
- The "Inscrito" pill is unconditionally true for every card a student ever sees (the query already filters to active enrollments only) — it carries zero information.
- Detector: `text-[10px]` status badge (line 115) is below DESIGN.md's type floor; see Design Specificity Verdict.

## Questions to Consider

1. If a Profesor and an Estudiante look at the same course, why does the card call its content "módulos" to one and "unidades" to the other — deliberate simplification, or just drift between two hand-maintained branches?
2. `modalidad` and `cupo_maximo` are two of the most concretely LearnUp-specific facts about a course — why does the one screen everyone opens daily show neither?
3. The stray `bg-menta-oscuro` button shows that keeping the Estudiante branch hand-rolled means every new feature is a fresh chance to leak the wrong palette. Does "two rooms, same building" have to mean "two foundations," or could Estudiante sit on `x-gestion` too and just swap tokens the way the header already does for Supervisor vs. Profesor?
