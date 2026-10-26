# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three roles, no self-registration: every account is created by a Supervisor.

- **Estudiante**: enrolled in one or more courses by the Supervisor. Consumes weekly content, submits tasks and quizzes, checks grades/attendance, messages their professor, and gets AI-assisted study help scoped to their own courses.
- **Profesor**: manages the courses they're assigned to (titular or co-docente). Builds weekly content (módulos), grades rubrics, tracks attendance, messages students, can't change course state/enrollment (Supervisor-only).
- **Supervisor**: the academic coordinator/admin. Creates and publishes courses, creates and manages every account, assigns professors, matricula students, reviews the immutable audit log, configures platform-wide settings.

## Product Purpose

An academic LMS (course content, grading, attendance, messaging, calendar) built as a personal/learning project — not commissioned by or tied to one real institution. The build itself follows real academic workflows closely (an ERS/requirements document drives most features) as a way to practice building a production-shaped system, not to compete commercially with existing LMS products.

## Positioning

Built around the Peru academic context rather than being a generic/international LMS:

- DNI (Peru national ID) is the account identifier and the auto-generated initial password; mandatory password change on first login.
- Dates, timezone, and formatting follow Peru conventions throughout.
- No self-service signup anywhere: every account, course, and enrollment is created and controlled by the Supervisor, with every administrative action logged to an append-only audit trail (database-enforced immutability, not just app-level).
- Course codes are auto-generated per a real sectioning pattern (subject prefix + year + sequence, e.g. `MAT1-26-01`), letting the same subject have several parallel sections/cycles without manual bookkeeping.

## Operating Context

- Course lifecycle: Supervisor creates a course (borrador by default, profesor assignment optional at creation — "assign later" leaves it under the Supervisor's own name as a placeholder), the Profesor adds weekly módulos and an evaluation scheme, then the Supervisor publishes once there's content and at least one enrolled student.
- Scheduling: a course has independent per-day schedule blocks (day, start/end time, and classroom when presencial) since the same course can meet on different days at different times/rooms; attendance sessions are auto-generated from that schedule.
- Modalidad: presencial (physical classroom), remoto (live/synchronous online), virtual (asynchronous, no fixed schedule).
- Enrollment: Supervisor-only, manual or via an external API integration; courses can have an optional capacity cap.
- No formal "ciclo académico"/term entity — each course's dates are independent, deliberately, so short cycles (e.g. a summer course) and regular-length cycles coexist without a rigid calendar.

## Capabilities and Constraints

- Course content: módulos organized by week, with tareas, quizzes, and a configurable evaluation rubric (esquema de evaluación) per course.
- Attendance: optional per course (`usa_asistencia`), sessions can be auto-generated from the schedule or added manually.
- Messaging: per-course, between a student and their professor(s).
- Calendar: role-aware, aggregates deadlines/sessions/events.
- AI study assistant: scoped strictly to the student's own enrolled-course data; no cross-course leakage.
- Audit log (bitácora): append-only, enforced at the database level (Postgres trigger blocks UPDATE/DELETE in production), not just in application code.
- Stack: Laravel + Livewire 3 + Blade + Tailwind CSS, Postgres (Supabase) in production, SQLite in-memory for tests.

## Brand Commitments

- Product name: **LearnUp**.
- Spanish-only UI copy; no English strings in user-facing text.
- Plain hyphens only in UI/chat copy — never em dashes or middle dots as separators. A standing, explicitly enforced style rule, not a one-off preference.
- Role-based palette: Estudiante and Profesor share one "corporate blue" palette (from a Figma-driven redesign); Supervisor keeps a separate warm accent palette (menta/terracota/arena) to stay visually distinct as the admin role.

## Evidence on Hand

- `database/seeders/DemoAcademicoSeeder.php` generates realistic demo data (courses, users, enrollments, grades) for local development. There is no real production content, no real testimonials/case studies — future work must not fabricate any.

## Product Principles

1. The Supervisor is the single source of truth for who exists and what they can access — no self-service account creation or enrollment anywhere in the product.
2. Every administrative action is auditable and the audit trail itself is tamper-proof, not just logged.
3. Peru-specific conventions (DNI, dates, Spanish) are load-bearing product decisions, not incidental defaults.
4. Scheduling and course structure stay flexible (per-course dates, per-day schedule blocks) rather than assuming one institutional calendar shape.
5. This is a learning project: favor doing things the real, production-shaped way over shortcuts, even though it isn't shipping to a real institution today.
