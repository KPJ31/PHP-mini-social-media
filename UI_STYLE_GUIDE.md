# Reusable UI Style Guide

## Purpose and scope

This guide captures the reusable visual language of this Laravel project. It is intended to be copied into another Laravel application so its existing content and functionality can be restyled consistently without copying this project's domain data, routes, controllers, models, migrations, or copy.

The source project has two related but technically distinct interfaces:

1. **Public website:** Tailwind CSS v4, Vite, Blade components, Poppins, Boxicons, and small vanilla-JavaScript modules. This is the primary visual system.
2. **Admin and authentication:** Bootstrap 5.3 plus `public/assets/css/admin.css`, with the same palette, typography, icon set, and surface language. Do not mix Bootstrap classes into public pages or Tailwind component classes into admin pages merely for convenience.

The shared visual character is confident and institutional: deep navy foundations, blue-violet brand accents, warm gold calls to action, white cards on a cool mist background, large rounded surfaces, restrained grid/mesh decoration, and clear Poppins typography.

## Source architecture

### Public frontend

- Entry layout: `resources/views/layouts/app.blade.php`
- Global styles and Tailwind v4 tokens: `resources/css/app.css`
- UI behavior: `resources/js/app.js`
- Shell partials: `resources/views/components/topbar.blade.php`, `header.blade.php`, and `footer.blade.php`
- Reusable UI components: `resources/views/components/ui/*.blade.php`
- Public pages: `resources/views/pages/*.blade.php`
- Vite inputs: `resources/css/app.css` and `resources/js/app.js`
- Tailwind is configured in CSS with `@theme`; there is no separate `tailwind.config.js`.

The public layout loads Poppins weights 400–800, Boxicons 2.1.4, the Vite bundle, and SweetAlert2. Pages use `@extends('layouts.app')`, metadata sections, `@push('jsonld')` where needed, and `@section('content')`.

### Admin and authentication

- Admin layout: `resources/views/layouts/admin.blade.php`
- Auth layout: `resources/views/layouts/auth.blade.php`
- Admin stylesheet: `public/assets/css/admin.css`
- Admin behavior: `public/assets/js/admin.js`
- Admin components: `resources/views/components/admin/*.blade.php`

These surfaces load Bootstrap 5.3.3, Poppins 400–700, Boxicons, SweetAlert2, and the custom admin stylesheet. CRUD views extend `layouts.admin`; login extends `layouts.auth`.

## Design foundations

### Color palette

Use semantic token names instead of scattering literal colors. Public Tailwind names and admin CSS variables map to the same values.

| Role | Public token/class | Admin variable | Value |
| --- | --- | --- | --- |
| Deepest navy | `navy-950` | `--a-navy-950` | `#0f1340` |
| Primary navy | `navy-900` | `--a-navy-900` | `#171e62` |
| Mid navy | `navy-800` | `--a-navy-800` | `#1d2b5a` |
| Navy link/accent | `navy-700` | — | `#203181` |
| Pale brand | `brand-50` | `--a-brand-50` | `#f2f5fe` |
| Soft brand | `brand-100` | `--a-brand-100` | `#e6ecfb` |
| Light brand | `brand-200` | `--a-brand-200` | `#c4d3f7` |
| Highlight blue | `brand-300` | `--a-brand-300` | `#85a7ee` |
| Bright brand | `brand-500` | `--a-brand-500` | `#4d6ce1` |
| Primary brand | `brand-600` | `--a-brand-600` | `#3756ca` |
| Brand hover/text | `brand-700` | `--a-brand-700` | `#253692` |
| Pale gold | `gold-300` | `--a-gold-300` | `#f8d27f` |
| CTA gold | `gold-400` | `--a-gold-400` | `#f2b84b` |
| Dark gold | `gold-500` | `--a-gold-500` | `#e0a12e` |
| Primary text | `ink-900` | `--a-ink-900` | `#07182f` |
| Secondary text | `ink-700` | — | `#33465c` |
| Body text | `ink-600` | `--a-ink-600` | `#526174` |
| Muted text | `ink-500` | `--a-ink-500` | `#64748b` |
| Subtle icon/text | `ink-300` | `--a-ink-300` | `#c2cbd6` |
| Page background | `mist-50` | `--a-mist-50` | `#f6f8fb` |
| Muted surface | `mist-100` | `--a-mist-100` | `#eef2f6` |
| Stronger divider | `mist-200` | — | `#e1e7ee` |

Semantic use:

- Public primary calls to action use gold (`bg-gold-400 text-navy-950`), not brand blue.
- Brand blue is used for links, focus, active states, icon backgrounds, filters, and secondary emphasis.
- Deep navy is used for heroes, footer, dark buttons, admin sidebar, and high-contrast bands.
- Page backgrounds are usually `mist-50`; cards and content sections alternate between white and `mist-50`.
- Public borders are usually `border-navy-900/8`, `/10`, or `/12`; admin uses `--a-line: rgba(15, 19, 64, 0.08)`.
- Dark surfaces use white at 45–90% opacity for hierarchy and `brand-300`/`gold-400` for highlights.
- Success, warning, and danger are functional colors rather than core brand tokens. Admin uses Bootstrap semantics: success around `#198754`, danger `#dc3545` (dark text `#c62839`), and warning/gold around `#f2b84b`. Public invalid fields use `red-500` with error text `red-700`.
- Global focus is `2px solid #3756ca` with a `4px` offset. Inputs use a `4px` translucent brand ring.

### Typography

The only interface family is **Poppins**, falling back to `ui-sans-serif`, `system-ui`, and sans-serif. Headings, navigation, buttons, pills, and eyebrows intentionally use the display alias, which currently resolves to the same Poppins family.

Public type patterns:

- Display/H1: `.h-display` = `text-4xl sm:text-5xl lg:text-6xl`, weight 700, line-height 1.05, tracking `-0.02em`. The home hero may increase to `2.75rem / 6xl / 7xl`.
- Section H2: `.h-section` = `text-3xl sm:text-4xl lg:text-[2.75rem]`, weight 700, line-height 1.1.
- Card heading: `.h-card` = `text-xl`, weight 700, `leading-snug`.
- Body lead: `.lead` = `text-base sm:text-lg`, `leading-8`, `text-ink-600`.
- Ordinary card/body copy: `text-sm leading-7 text-ink-600` or `text-base leading-8`.
- Label: `text-sm font-semibold text-ink-900`.
- Helper/meta text: `text-xs` or `text-[11px]`, usually `font-semibold`, `text-ink-500`.
- Eyebrow: `11px`, weight 700, uppercase, tracking `0.22em`, `brand-700`, with a small glowing dot.
- Pill: `11px`, weight 700, uppercase, tracking `0.14em`.
- Navigation: `14px`, weight 600.
- Button: `14px`, weight 600; large buttons use `15px`, small buttons `12px`.

Do not use excessive font weights. Normal prose is 400; supporting emphasis is 500/600; headings and compact labels are 700. Weight 800 is available but is not the default.

### Layout, spacing, radius, and shadow

The standard public container is:

```html
<div class="container-x">
    <!-- mx-auto w-full max-w-[1320px] px-4 sm:px-6 lg:px-8 -->
</div>
```

Recurring layout rules:

- Full-width sections contain one `.container-x`; never apply maximum width to the decorative section itself.
- Normal major sections use `py-20 md:py-28`. CTA bands commonly use `py-20 md:py-24`.
- Page heroes use `py-20 sm:py-24 lg:py-28`; compact heroes use `py-16 sm:py-20`.
- Section heading to grid/content gap is commonly `mt-12` or `mt-14`.
- Grid gaps are usually `gap-5`, `gap-6`, or `gap-8`; major split layouts use `gap-12`, increasing to `lg:gap-16` or `lg:gap-20`.
- Most public responsive grids start with one column, become two at `md`, and three or four at `lg`.
- Standard cards use `rounded-3xl` (24px). Feature images and bento tiles often use `rounded-[2rem]` (32px). CTA panels use `rounded-[2.5rem]` (40px).
- Compact controls use `rounded-lg` or `rounded-xl`; form inputs use `rounded-2xl`; pills are fully rounded.
- Standard public card padding is `p-6 sm:p-7`; roomier feature panels use `p-8 sm:p-12 lg:p-16`.
- Default card shadow `.shadow-soft` is `0 12px 40px -12px rgba(15,19,64,.12)`.
- Elevated/hover shadow `.shadow-lift` is `0 30px 60px -20px rgba(15,19,64,.28)`.
- Brand glow is `0 18px 40px -12px rgba(77,108,225,.45)`.

Avoid indiscriminate shadows. Use a thin translucent navy border with `shadow-soft`; reserve `shadow-lift` for heroes, floating compositions, modal imagery, and interactive hover elevation.

## Public page anatomy

```text
layouts.app
├── Topbar (40px, dark navy)
├── Sticky header (80px; 68px after scroll)
├── #main-content
│   └── Page <main>
│       ├── Home hero or <x-ui.page-hero>
│       ├── Alternating white / mist content sections
│       ├── Cards, grids, forms, or detail content
│       └── <x-ui.cta-band> where appropriate
├── Footer (dark navy)
└── Back-to-top control and feedback scripts
```

New public pages should normally:

1. Extend `layouts.app` and define title/meta sections.
2. Wrap content in `<main class="overflow-hidden bg-mist-50 text-ink-900">` where overflow decorations are present.
3. Start with `<x-ui.page-hero>` for interior pages.
4. Alternate white and mist sections rather than placing every section on the same surface.
5. Use `.container-x`, `.h-section`, `.lead`, `.card`, and existing Blade components before inventing variants.
6. End high-intent pages with the existing CTA-band pattern.

## Components

### Topbar

- Fixed visual height: `h-10` (40px), `bg-navy-950`, white text, `z-[51]`.
- Left side holds phone at all widths, email from `sm`, and opening hours from `lg`.
- Right utility links and admissions pill appear from `md`; social icon circles remain compact.
- Text is `12.5px`; normal text is `white/60–80`; icons are `brand-400`; hover goes to `brand-300` or a translucent white/brand background.
- Keep low-priority information hidden on narrow screens rather than wrapping the bar taller.

### Header and navigation

- Sticky at the top with `z-50`, `bg-white/90`, `backdrop-blur-xl`, and a subtle bottom border.
- Header bar is 80px and shrinks to 68px after 24px of scroll. Logo is 48px high, 52px from `sm`.
- Desktop navigation appears only at `xl` (1280px) and uses `gap-7`.
- Active/hover links turn `brand-700` and grow a 2px brand underline from the left.
- Desktop dropdowns are 21rem wide, rounded 16px, white, with a fine border and deep floating shadow. Items have a 36px icon tile, title, hint, and optional arrow.
- The primary header CTA is hidden below `sm`; the menu button is shown below `xl`.
- Mobile navigation is an 88%-width, maximum 24rem right drawer over a navy translucent blurred overlay. It locks body scrolling, traps keyboard focus, closes on Escape/backdrop/link activation, and uses expandable grouped links.
- Preserve `aria-expanded`, `aria-controls`, `aria-current`, dialog semantics, and keyboard handling.

### Footer

- `bg-navy-950 text-white`, with a faint 48px grid and a gradient top rule.
- Top row: brand block plus outline and gold CTAs; stacks until `lg`.
- Main content: one column by default, two at `sm`, and `1.5fr 1fr 1fr 1fr` at `lg`.
- Use `py-12`, `gap-y-10`, small body copy (`text-sm leading-7 text-white/60`), and uppercase 13px link-column headings.
- Social controls are 40px rounded squares with subtle border/background; hover fills brand blue.
- Accreditation strip and bottom legal row use 12px muted text and 1px white/10 separators. Bottom row switches to horizontal at `md`.

### Page hero and breadcrumbs

Prefer `<x-ui.page-hero>` for interior pages. It provides:

- Deep navy background, optional cover image using `object-cover`, two dark gradient overlays, grid texture, and blurred brand/gold orbs.
- Standard or compact vertical padding, left or centered alignment, optional eyebrow, actions, and aside slot.
- Breadcrumbs above the title with 12px semibold white/60 text; current item uses `brand-300`.
- Content maximums of `max-w-4xl` for headings and `max-w-3xl` for body text.

### Section heading

Use `<x-ui.section-heading>` instead of rebuilding headings. It supports eyebrow, title, highlighted title suffix, light/dark surfaces, centered alignment, and heading element selection. Centered headings use `mx-auto max-w-3xl text-center`; descriptions are `mt-5 text-base leading-8`.

### Buttons and links

Base public button:

```html
<a class="btn btn-primary" href="#">
    Action <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
</a>
```

Variants:

- `.btn-primary`: gold surface, navy text, warm shadow; use for the single primary action in a cluster.
- `.btn-dark`: deepest navy with white text; useful on light surfaces.
- `.btn-white`: white with navy text.
- `.btn-outline`: subtle navy border on white; hover becomes pale brand.
- `.btn-outline-light`: translucent white treatment for navy/image surfaces.
- `.btn-lg`: 28px horizontal/16px vertical padding and 12px radius.
- `.btn-sm`: 16px horizontal/10px vertical padding and 6px radius.
- `.link-arrow`: compact bold brand link with an arrow translating 4px on hover.

Buttons scale to 1.02 on hover and 0.99 active, with 200ms transitions. Use `.shine` sparingly on major gold CTAs. Icon buttons are generally square/circular, 40–56px, with an explicit accessible label.

### Cards

Standard card:

```html
<article class="card card-hover group overflow-hidden">
    <div class="relative aspect-[16/10] overflow-hidden">
        <img class="h-full w-full object-cover transition duration-700 group-hover:scale-105" alt="">
    </div>
    <div class="p-6 sm:p-7">
        <h3 class="h-card text-ink-900">Card Title</h3>
        <p class="mt-3 text-sm leading-7 text-ink-600">Generic supporting copy.</p>
    </div>
</article>
```

`.card` is white, rounded 24px, bordered with navy/8, and softly shadowed. `.card-hover` lifts 2px, scales to 1.02, strengthens the border, and uses the lift shadow with a slow premium easing. Image cards use 16:10 most often; portrait/feature media may use 4:3. Images use `object-cover`, lazy loading below the fold, and a slow 700–1000ms hover zoom. Empty image states use `.bg-mesh` with a large Boxicon.

Dark cards use `.card-dark`: white/4 background, white/10 border, 24px radius, and backdrop blur. Small feature rows may use `rounded-3xl border ... bg-mist-50 p-5` without a shadow.

### Badges, pills, and icon tiles

- `.pill-brand`: pale blue with brand text.
- `.pill-navy`: navy with white text.
- `.pill-gold`: translucent gold with dark-gold text.
- `.pill-light`: translucent white with blur for dark/image surfaces.
- `.icon-badge`: 48px square, 16px radius, pale brand background, 24px icon. Inside hover cards it fills brand blue and scales slightly.
- Status pills should use semantic color and concise text; do not use gold to mean danger.

### Forms

Public form field:

```blade
<div class="min-w-0">
    <label for="field" class="label">Field Label <span class="text-brand-600">*</span></label>
    <input id="field" name="field" class="input" aria-describedby="field-help">
    <p id="field-help" class="mt-2 text-xs text-ink-500">Helpful guidance.</p>
</div>
```

- Use grid spacing around fields, usually `grid gap-5 sm:grid-cols-2`.
- Inputs are full width, rounded 16px, white, 14px horizontal/vertical padding (`px-4 py-3.5`), 15px text, subtle inset shadow, and navy/12 border.
- Hover strengthens the border; focus uses `brand-500` plus a `ring-4 ring-brand-500/15`.
- Invalid fields set `aria-invalid="true"`, red border/ring, and connect to `.field-error` via `aria-describedby`.
- Textareas use the same `.input`, `resize-y`, and usually four or more rows.
- Selects use the same class with a custom inline SVG chevron and right padding.
- Checkboxes are 20px, rounded 5px, with brand accent.
- Optional labels append normal-weight muted `(optional)` text. Required labels use a brand-colored asterisk, while `required` remains on the control.
- Loading forms use `data-loading-form` and `data-loading-text`; submission replaces button content with a spinner and disables duplicate submission.
- Success feedback uses an inline rounded pill with a check icon. General session feedback is handled by SweetAlert2; validation also remains visible inline.
- No native public radio style is established. If needed, derive it from the checkbox/input focus treatment rather than introducing a new library.

### Tables, filters, and pagination

Public pages do not establish a reusable data-table language; tabular CRUD is an admin pattern. Do not invent public tables unless the target content is truly tabular.

Admin tables use `.table.admin-table.align-middle` inside `.table-responsive` and a `.content-card p-4`. Headers are compact uppercase/muted, rows have thin dividers, primary/secondary cell text is stacked, and actions are grouped at the right. Below 992px the table keeps a minimum 720px width and scrolls horizontally with edge-shadow hints; it does not collapse rows into cards. Pagination uses Laravel's `pagination::bootstrap-5` view.

Admin filters use `<x-admin.filter-bar>`: mist background, 12px radius, 14–16px padding, flexible search, 150–190px selects, dark Apply button, optional Reset, result count, and active-filter chip. Select/date filters auto-submit via `data-auto-submit`.

### Alerts and notifications

- Public global notifications use SweetAlert2 with a 2600ms timer, progress bar, brand-blue confirm button, and rounded-3xl popup.
- Public form errors remain inline and accessible even when a toast is shown.
- Admin validation summaries use Bootstrap `.alert.alert-danger`; row/form errors use `.invalid-feedback`.
- Admin destructive actions use a SweetAlert2 confirmation with danger-red confirmation. Logout uses a question confirmation.

### Dropdowns, accordion, modal/lightbox, and tabs

- Public desktop navigation dropdowns and mobile accordions are custom vanilla JavaScript/CSS as described above.
- FAQ accordions use `.accordion-item`, `.accordion-trigger`, `.accordion-icon`, and `.accordion-panel`, with one item open at a time. The plus icon rotates 45 degrees and fills brand blue. The panel animates grid rows over 400ms.
- The gallery lightbox is the established public modal: fixed full viewport at `z-[9999]`, navy/95 backdrop, large rounded contained image, close/previous/next buttons, title and counter. It supports Escape, arrow keys, keyboard cycling, backdrop close, focus restoration, and touch swipe.
- Admin account dropdowns use Bootstrap dropdown behavior, then receive 12px radius, fine border, soft shadow, 8px item radius, and pale-brand hover styling.
- No reusable tab component is established. Do not create one unless required by target functionality; match card, border, pill, and focus tokens if one is necessary.

### Statistics and empty states

Public statistics use `<x-ui.stat>`, with 36–48px bold values, optional 44px/48px icon tiles, and muted 14px labels. Numeric values animate only when motion is permitted.

Admin `.stat-card` uses a white 16px-radius surface, 20px padding, 44px icon tile, 28px value, and hover lift. On phones padding and type scale down.

Empty states are quiet, centered, and helpful: muted 13–14px copy, a 32px subtle icon, and an optional action. Do not overdecorate or use alarming colors.

### CTA band

Use `<x-ui.cta-band>` for a strong page-ending conversion block: 40px radius, mesh navy background, grid overlay, blurred blue/gold orbs, `p-8 sm:p-12 lg:p-16`, white copy, and one gold plus optional light-outline action. It stacks vertically and becomes a horizontal split at `lg`.

## Responsive rules

The project uses Tailwind's standard breakpoints: `sm` 640px, `md` 768px, `lg` 1024px, `xl` 1280px, and `2xl` 1536px where needed. The 1320px container prevents uncontrolled expansion on large displays.

### Mobile, below 640px

- One-column sections and full-width stacked action buttons where space is tight.
- Container padding is 16px.
- Header CTA is hidden; topbar shows only essential contact/social content.
- Navigation uses the right drawer.
- Display type starts around 36–44px depending on hero; section headings start at 30px.
- Cards use 24px padding and retain generous 24px radii.
- Footer is a single column; legal text is centered.
- Lightbox controls are 48px and placed close to viewport edges.

### Small tablet, 640–767px

- Container padding becomes 24px.
- Header CTA appears.
- Forms and selected content grids often become two columns using `sm:grid-cols-2`.
- Footer becomes two columns while the descriptive block spans both.
- Typography steps up (`sm:text-*`).

### Tablet/laptop, 768–1023px

- Major grids commonly become two columns.
- Section spacing increases from 80px to 112px.
- Statistic strips often become four columns at `md`.
- Footer bottom bar becomes horizontal at `md`.
- Admin sidebar remains an off-canvas overlay below 992px, and data tables scroll horizontally.

### Desktop, 1024–1279px

- Split layouts and bento grids activate at `lg`.
- Feature imagery that is hidden on small screens may appear.
- CTA bands align content and actions horizontally.
- Public navigation still uses the drawer until `xl`.
- Admin sidebar is fixed from Bootstrap's 992px `lg` breakpoint; main content gains a 272px left margin.

### Large desktop, 1280px and above

- Full desktop public navigation appears and the menu button disappears.
- Three/four-column grids are common.
- The public page remains capped at 1320px with 32px side padding.
- Avoid adding density simply because space is available; preserve the established maximum line widths.

## Images and icons

- Boxicons 2.1.4 is the sole icon library. Use `bx` plus the relevant icon class. Decorative icons receive `aria-hidden="true"`; icon-only controls receive an `aria-label`.
- Typical inline icon size is 16–20px; icon badges are 20–24px; empty/placeholder icons are 32–64px.
- Content card images are generally 16:10; editorial/feature images are often 4:3. Use explicit `aspect-*`, full width/height, and `object-cover` to prevent layout shift.
- Hero media fills its section with `object-cover object-center` and layered navy gradients to keep white text legible.
- Modal images use `object-contain`, not crop.
- Below-fold images use `loading="lazy"`; hero images use `fetchpriority="high"`. Meaningful images need useful alt text; decorative layers use empty alt plus `aria-hidden`.
- Image placeholders use the navy mesh background and a subdued `brand-300` Boxicon.
- Over-image text uses `.img-tint` or an equivalent navy gradient rather than relying on the source image's darkness.

## Motion and interaction

Motion is polished but controlled:

- Buttons: 200ms, tiny scale/translation.
- Navigation/dropdowns: 200–300ms.
- Cards: 450–550ms with `cubic-bezier(.22,1,.36,1)` and only a 2px lift/1.02 scale.
- Card images: 700–1000ms zoom to 1.05.
- Scroll reveals: 800–900ms fade/translate or scale, with 100ms stagger steps.
- Decorative floating elements: 7s or 11s; marquees: 38s and pause on hover.
- Hero parallax is subtle (typically 0.15–0.18).

The root `.js` class ensures reveal hiding is only enabled when JavaScript is available. All motion respects `prefers-reduced-motion`; smooth inertia is also disabled on coarse pointers. Keep this progressive-enhancement strategy. Do not introduce a client framework for these interactions.

## JavaScript conventions

Public behavior is modular vanilla JavaScript plus Lenis:

- Lenis smooth scrolling on fine pointers only, paused when the body is scroll-locked.
- Sticky header compact state.
- Accessible desktop and mobile navigation.
- Back-to-top visibility and scrolling.
- IntersectionObserver reveal and numeric counters.
- Duplicate-submit prevention/loading states.
- Accessible gallery lightbox with swipe.
- Auto-submit filters.
- Subtle hero parallax.
- Form feedback scrolling/focus.
- Single-open accordion groups.

Admin behavior uses Bootstrap's JS for dropdowns and vanilla modules for off-canvas sidebar state, password visibility, SweetAlert confirmations, duplicate-submit prevention, auto-submit filters, and drag/drop file preview. Extend the existing approach; do not add Alpine, Vue, React, jQuery, or another interaction library without a demonstrated requirement.

## Blade development conventions

- Public pages use `@extends('layouts.app')`; admin pages use `@extends('layouts.admin')`; login uses `@extends('layouts.auth')`.
- Public shell pieces use `@include`; reusable parameterized UI uses anonymous components such as `<x-ui.page-hero>`, `<x-ui.section-heading>`, `<x-ui.programme-card>`, and `<x-ui.cta-band>`.
- Components define inputs with `@props`, accept default slots, and use named slots for structured additions such as `actions` or `aside`.
- Use `route()` for internal navigation and `asset()` for public assets. Existing uploaded public files are guarded with `is_file(public_path(...))` before rendering and have a visual fallback.
- Preserve semantic elements (`main`, `section`, `nav`, `article`, `dl`, `address`, `time`) and accessible naming.
- Active routes use `request()->routeIs(...)` and `aria-current="page"`.
- Conditional UI uses Blade directives (`@if`, `@forelse`, `@can`, `@error`, `@selected`, `@checked`) rather than duplicating backend decisions in JavaScript.
- Forms use `@csrf`; destructive forms use `@method('DELETE')` and authorization-aware rendering.
- Rich text is escaped unless it has a deliberate, sanitized rendering path. Avoid copying source-specific text into reusable components.
- Public page metadata belongs in Blade sections; structured data is pushed with `@push('jsonld')`.
- Prefer extracting a component only when the pattern is repeated or has meaningful behavior/slots. Do not redesign the current Blade architecture.

## Admin-specific visual system

The admin UI deliberately uses Bootstrap structure with a branded override layer.

- Shell: fixed 272px navy-gradient sidebar from 992px upward; off-canvas below it. Sticky 72px translucent-white top header.
- Content padding: 20px/24px on smaller screens, 32px/48px desktop; phone padding is 14px/18px.
- Cards: `.content-card` = white, 16px radius, line border, `0 12px 40px -14px rgba(15,19,64,.14)`.
- Page header: reusable `<x-admin.page-header>`, 24px title, 13.5px muted subtitle, actions at right; actions become full-width/flexible on phones.
- Primary CRUD action: `.btn-gold`; brand-blue `.btn-primary` is a secondary administrative emphasis. Danger actions use `.btn-outline-danger`.
- Inputs: Bootstrap `.form-control`/`.form-select` overridden to 10px radius, 14px text, 10px/14px padding, brand focus ring.
- Form groups use Bootstrap rows with `g-3`; groups are separated by `.form-section-title`, an uppercase 12px label followed by a rule. Form buttons live in `.form-actions` with a top divider.
- Admin status, chip, avatar, row-action, empty-state, upload, and filter classes should be reused instead of rebuilt per CRUD screen.
- Login uses a centered `.login-card`: image-backed navy visual panel plus white form panel, becoming a two-column card at 900px.

## Reusable examples

### Public page skeleton

```blade
@extends('layouts.app')

@section('title', 'Page Title')
@section('meta_description', 'A concise page description.')

@section('content')
<main class="overflow-hidden bg-mist-50 text-ink-900">
    <x-ui.page-hero eyebrow="Section" title="Page Title" :crumbs="['Page Title' => null]">
        A short, useful introduction to this page.
    </x-ui.page-hero>

    <section class="bg-white py-20 md:py-28">
        <div class="container-x">
            <x-ui.section-heading eyebrow="Overview" title="Section Title">
                Supporting copy that explains the section.
            </x-ui.section-heading>
            <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3" data-stagger>
                <!-- cards -->
            </div>
        </div>
    </section>
</main>
@endsection
```

### Public form and alert

```blade
@if (session('success'))
    <p role="status" class="mb-5 inline-flex items-center gap-2 rounded-full bg-brand-100 px-4 py-2 text-sm font-semibold text-brand-700">
        <i class="bx bx-check-circle text-lg" aria-hidden="true"></i>{{ session('success') }}
    </p>
@endif

<form method="POST" action="{{ route('form.submit') }}" class="card p-6 sm:p-8" data-loading-form>
    @csrf
    <div class="grid gap-5 sm:grid-cols-2">
        <div class="min-w-0">
            <label for="name" class="label">Name <span class="text-brand-600">*</span></label>
            <input id="name" name="name" class="input" required
                @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p id="name-error" role="alert" class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
    <button type="submit" class="btn btn-primary mt-6" data-loading-text="Sending…">
        Submit <i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
    </button>
</form>
```

### Admin table skeleton

```blade
<x-admin.page-header eyebrow="Content" title="Items" subtitle="Manage the items shown in this area.">
    <a href="{{ route('admin.items.create') }}" class="btn btn-gold"><i class="bx bx-plus"></i> Add Item</a>
</x-admin.page-header>

<div class="content-card p-4">
    <x-admin.filter-bar :action="route('admin.items.index')" :paginator="$items" />
    <div class="table-responsive">
        <table class="table admin-table align-middle">
            <thead><tr><th>Item</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
            <tbody><!-- semantic rows and empty state --></tbody>
        </table>
    </div>
    {{ $items->links('pagination::bootstrap-5') }}
</div>
```

## Component quick reference

| Component | Main style | Important notes |
| --- | --- | --- |
| Page container | `.container-x`: max 1320px, `px-4 sm:px-6 lg:px-8` | One inside each full-width section |
| Topbar | 40px, navy-950, 12.5px white/muted text | Hide low-priority content responsively |
| Navbar | Sticky white/90 blur, 80→68px | Desktop at `xl`; accessible right drawer below |
| Page hero | Navy, optional cover image, gradients/grid/orbs | Prefer `<x-ui.page-hero>` |
| Section heading | Eyebrow + `.h-section`, max 3xl | Prefer `<x-ui.section-heading>` |
| Primary button | `.btn.btn-primary`, gold with navy text | One primary action per cluster |
| Secondary button | `.btn.btn-dark`, `.btn-outline`, or `.btn-outline-light` | Choose by surface contrast |
| Card | `.card`, white, 24px radius, fine border, soft shadow | Add `.card-hover` only if interactive |
| Input | `.input`, 16px radius, 15px text, brand focus ring | Pair label, errors, and ARIA references |
| Badge/pill | `.pill` plus semantic variant | 11px uppercase; concise labels only |
| Public alert | Inline accessible status + SweetAlert2 global feedback | Never rely on toast alone for validation |
| Admin table | `.table.admin-table` in `.table-responsive` | Horizontal scroll below 992px |
| Admin filter | `<x-admin.filter-bar>` | Supports search, filters, count, reset |
| Lightbox | Navy/95 full-screen modal | Keyboard, focus, arrows, swipe |
| Footer | Navy-950, grid texture, responsive columns | Small muted copy and brand/gold accents |

## Design token summary

```text
DESIGN TOKENS

Primary: Gold 400 #f2b84b (primary CTA); Brand 600 #3756ca (interactive system color)
Secondary: Navy 950 #0f1340 / Navy 900 #171e62
Accent: Brand 300 #85a7ee and Gold 300 #f8d27f

Page Background: Mist 50 #f6f8fb
Card Background: White #ffffff

Primary Text: Ink 900 #07182f
Secondary Text: Ink 600 #526174
Muted Text: Ink 500 #64748b

Border: rgba(15, 19, 64, 0.08) (typically navy-900/8)
Success: Bootstrap green #198754 family
Warning: Gold 400 #f2b84b / Bootstrap warning semantics
Danger: Red #dc3545; dark danger text #c62839; public error text Tailwind red-700

Font Family: Poppins, ui-sans-serif/system-ui fallback

Container: max-width 1320px; px 16px / 24px / 32px
Section Spacing: py-20 (80px), md:py-28 (112px); CTA md:py-24 (96px)

Small Radius: 8–12px (rounded-lg/xl; controls)
Default Radius: 16px inputs/admin cards; 24px public cards
Large Radius: 32px feature media; 40px CTA panels

Default Shadow: 0 12px 40px -12px rgba(15,19,64,0.12)
Admin Shadow: 0 12px 40px -14px rgba(15,19,64,0.14)
Lift Shadow: 0 30px 60px -20px rgba(15,19,64,0.28)

Mobile Breakpoint: base / sm 640px
Tablet Breakpoint: md 768px
Desktop Breakpoint: lg 1024px; public desktop navigation at xl 1280px
Admin Fixed Sidebar Breakpoint: Bootstrap lg 992px
```

## Rules for future AI/Codex

1. Read this entire file before modifying frontend UI.
2. Inspect the target project's existing Laravel, Blade, CSS, and JavaScript architecture before editing.
3. Preserve the target project's routes, controllers, models, migrations, database behavior, authorization, validation, and business functionality.
4. Apply this design system to the target architecture; do not blindly copy source files.
5. Do not copy routes, controllers, models, migrations, database logic, permissions, or business rules from the source project.
6. Do not copy source-project names, statistics, contact details, institution-specific text, images, or business data.
7. Reuse the visual language—not the source business domain.
8. Follow the documented palette, typography, spacing, radii, shadows, components, motion, and responsive behavior.
9. Keep Blade code consistent with the target project's established architecture and component conventions.
10. Keep existing route names and backend functionality unless explicitly asked to change them.
11. Use Tailwind for a target public surface when adopting the public design. Do not introduce Bootstrap there.
12. If adapting the admin style, use Bootstrap only when the target already uses it or migration is explicitly authorized; otherwise translate the visual tokens to its existing stack.
13. Do not introduce unnecessary UI libraries or a JavaScript framework. Prefer existing dependencies and small vanilla modules.
14. Maintain responsive behavior at every documented breakpoint; verify narrow mobile, tablet, desktop, and large desktop layouts.
15. Maintain accessibility: semantic HTML, explicit labels, visible focus, contrast, ARIA state, keyboard access, reduced motion, and focus management.
16. New pages must look native to this design system, including alternating surfaces, restrained decoration, proper line lengths, and consistent section rhythm.
17. Prefer existing reusable Blade components/partials in the target project. Extract a new component when a pattern repeats or carries meaningful behavior.
18. Never redesign an existing component without a clear reason or explicit instruction.
19. Preserve content hierarchy: one dominant H1, clear H2 sections, compact eyebrows, restrained pills, and one visual primary action per action group.
20. Do not invent components claimed to exist. Where this guide notes no established pattern (for example public data tables or tabs), derive any necessary addition conservatively from existing tokens.
21. Preserve progressive enhancement. Content must remain usable if optional motion or JavaScript does not run.
22. Before finishing, compare changes against representative target pages: landing/hero, listing/cards, detail content, form, mobile navigation, footer, and—if in scope—admin table/form.

## Verification basis

This guide was checked against the source's home page, interior hero/content pages, programme and news/event cards, event detail, gallery/lightbox, contact and newsletter forms, global topbar/header/footer, admin dashboard, CRUD event listing/table, CRUD event form, and administrator login. The rules above describe repeated implementation patterns rather than isolated visual experiments.
