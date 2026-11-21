# Design System

The direction was set with the Hallmark design skill. Genre: **modern-minimal** (SaaS platform). Theme: **Console** (dealt 2 of 3), adapted for CyberCompass. Colourway: **navy graphite**, with cyan in place of Console's amber, as the brief asks.

> An instrument kept by careful people. A navy-graphite ground stepped in three lightnesses, hairline panels cut from one surface, a calm grotesk for what a person reads, and a mono face for what was measured: scores, durations, IDs, commands. One cyan signal means *you*: your position, your progress, your next action. Nothing glows.

It is **not** a neon hacker terminal, a glassmorphism dashboard, matrix rain, or a sci-fi control room. Security is serious work, and the UI should feel like a well-kept lab notebook.

## 1. Principles

1. **One idea per screen.** Every screen has one lead element set large: the roadmap, the current lesson, the score. Everything else supports it.
2. **The product is the picture.** Marketing pages show the real roadmap, a real result and a real exercise, rendered from the components. No stock "hacker in a hoodie" photos and no illustrations.
3. **Two meanings, never swapped.** Cyan is *the learner* (you are here, your progress, primary action). Amber is *attention* (a knowledge gap, a streak at risk, review suggested). Green and red mean right or wrong, and appear only in exercise feedback and diffs.
4. **Measured things look measured.** Scores, durations, timestamps, IP addresses, ports and commands use the mono face with tabular figures. Labels, headings and buttons never use mono.
5. **State over decoration.** Every colour, border change and motion reports a state change. Nothing is ornamental.

## 2. Colour tokens

Defined once in `frontend/src/styles/tokens.css` in OKLCH. Components use only role tokens, never literal colours.

| Role | Dark (default) | Light | Used for |
|---|---|---|---|
| `--color-paper` | `oklch(17% 0.014 255)` | `oklch(98.4% 0.004 250)` | Page ground |
| `--color-paper-2` | `oklch(21% 0.016 255)` | `oklch(100% 0 0)` | Panels, one step up |
| `--color-paper-3` | `oklch(13.5% 0.012 255)` | `oklch(95.6% 0.006 250)` | Deepest step: inputs, code, terminals, bands |
| `--color-ink` | `oklch(95% 0.006 250)` | `oklch(20% 0.02 258)` | Headings, primary text |
| `--color-ink-2` | `oklch(83% 0.01 250)` | `oklch(32% 0.02 258)` | Lede, emphasized body |
| `--color-muted` | `oklch(71% 0.014 250)` | `oklch(46% 0.018 255)` | Body, labels, captions |
| `--color-rule` | `oklch(31% 0.016 255)` | `oklch(89% 0.008 255)` | Hairlines, panel seams |
| `--color-rule-2` | `oklch(42% 0.018 255)` | `oklch(78% 0.012 255)` | Selected row, outline controls, graph edges |
| `--color-field-edge` | `oklch(54% 0.018 255)` | `oklch(62% 0.015 255)` | Input boundary: 3:1 against its surface (WCAG 1.4.11) |
| `--color-accent` | `oklch(82% 0.13 190)` | `oklch(52% 0.11 195)` | **The learner**: primary button, current node, progress fill, links on hover |
| `--color-accent-ink` | `oklch(18% 0.04 195)` | `oklch(99% 0 0)` | Text on an accent fill |
| `--color-accent-soft` | `oklch(29% 0.05 190)` | `oklch(94% 0.03 195)` | In-progress tint, selected chip |
| `--color-attn` | `oklch(82% 0.15 80)` | `oklch(58% 0.14 70)` | **Attention**: gap band, streak at risk, "review" recommendation |
| `--color-attn-soft` | `oklch(29% 0.05 80)` | `oklch(95% 0.04 80)` | Attention tint |
| `--color-ok` / `-soft` | `oklch(76% 0.15 150)` / `oklch(24.5% 0.03 150)` | `oklch(52% 0.14 150)` / `oklch(95% 0.04 150)` | Correct answer, passed checkpoint |
| `--color-bad` / `-soft` | `oklch(70% 0.17 25)` / `oklch(24.5% 0.035 25)` | `oklch(55% 0.19 27)` / `oklch(95.5% 0.03 25)` | Incorrect answer, destructive action, form error |
| `--color-focus` | `= accent` | `= accent` | Focus ring |

Contrast target: **WCAG AA** for all text (4.5:1 body, 3:1 large text and UI boundaries) in both themes. The sweep checks this.

**Theme switching:** `next-themes` puts `class="dark"` or `"light"` on `<html>`. "System" follows `prefers-color-scheme`. Tokens live under `:root` (light) and `.dark`, and `color-scheme` is set in both so native controls and scrollbars follow.

### shadcn/ui mapping

shadcn variables are aliases to the role tokens, so the primitives inherit the system:

```css
--background: var(--color-paper);        --foreground: var(--color-ink);
--card: var(--color-paper-2);            --card-foreground: var(--color-ink);
--popover: var(--color-paper-2);         --popover-foreground: var(--color-ink);
--primary: var(--color-accent);          --primary-foreground: var(--color-accent-ink);
--secondary: var(--color-paper-3);       --secondary-foreground: var(--color-ink);
--muted: var(--color-paper-3);           --muted-foreground: var(--color-muted);
--accent: var(--color-paper-3);          --accent-foreground: var(--color-ink);   /* shadcn "accent" = hover surface */
--destructive: var(--color-bad);         --border: var(--color-rule);
--input: var(--color-rule);              --ring: var(--color-focus);
--radius: 10px;
```

## 3. Typography

| Role | Face | Size / weight | Notes |
|---|---|---|---|
| Display (h1, hero) | **Be Vietnam Pro** 500 | `--text-display` clamp(2.75rem → 5.25rem), lh 1.04, tracking −0.03em | Sentence case, roman, never bold 700 |
| Section head (h2) | Be Vietnam Pro 500 | `--text-section` clamp(2rem → 3.5rem) | |
| Title (h3, panel titles) | Be Vietnam Pro 500 | `--text-title` clamp(1.25rem → 1.625rem) | |
| Body | Be Vietnam Pro 400 | 16 to 17px, lh 1.55, `--color-muted` | Lesson prose: 18px, measure 68ch, `--color-ink-2` |
| Label, caption, table head, chip, nav | Be Vietnam Pro 500 | one step down (`--text-sm`) | **Sentence case, untracked, never all caps** |
| Measured | **JetBrains Mono** 400/500 | 14 to 15px; display size for a lead score | `tabular-nums slashed-zero`. Scores, times, IDs, commands, IPs. |

**Why these faces:** Console specifies Host Grotesk and Atkinson Hyperlegible Mono, but neither ships a Vietnamese subset, and Vietnamese is a required UI language. Be Vietnam Pro is a neo-grotesk drawn for Vietnamese diacritics, and JetBrains Mono covers Vietnamese and has a slashed zero. Both load through `next/font/google` with `subsets: ['latin', 'vietnamese']`, self-hosted at build time.

Scale: `--text-xs .8125rem · sm .875rem · md 1rem · lg 1.25rem · xl 1.5625rem · 2xl 1.953rem · 3xl 2.441rem`.

## 4. Space, radius, depth, motion

```css
--space-3xs: 2px; --space-2xs: 4px; --space-xs: 8px; --space-sm: 12px; --space-md: 16px;
--space-lg: 24px; --space-xl: 40px; --space-2xl: 64px; --space-3xl: 96px; --space-4xl: 144px;
--container: 1440px; --gutter: 64px (24px < 768px, 16px < 400px); --gap-columns: 32px;
--radius-card: 10px; --radius-control: 6px; --radius-chip: 4px; --rule-hair: 1px;
--shadow-card: none;   /* depth comes from the three paper steps, never shadows */
--shadow-overlay: 0 12px 32px oklch(0% 0 0 / 0.35);   /* menus, dialogs only */
--ease-out: cubic-bezier(0.16, 1, 0.3, 1); --ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);
--dur-micro: 120ms; --dur-short: 220ms; --dur-long: 420ms;
--z-dropdown: 100; --z-sticky: 200; --z-sticky-nav: 300; --z-modal: 400; --z-toast: 500; --z-tooltip: 600;
```

**Motion character: brisk and functional.** A value animates when its source changes: a progress bar fills, a roadmap node expands, a score counts up once. Only `transform` and `opacity` animate, over `--dur-short` with `--ease-out`. No scroll-triggered reveals, parallax or hover lifts. Under `prefers-reduced-motion: reduce`, every transition ends immediately. Motion is the library used for layout transitions such as roadmap expand/collapse and exercise feedback.

## 5. Surfaces and layout

- **Hairline panels.** A panel is `--color-paper-2` inside a 1px `--color-rule`, radius 10px. Adjacent panels form **one surface divided by 1px seams** (a grid with `gap: 1px` on a rule-coloured background). That replaces stacks of separate rounded cards.
- **Never a card inside a card.** Inside a panel, group with spacing or seams.
- **Three depths, no lamp.** Ground, then panel, then deepest (inputs, code, terminal, the changed row). No glow, bloom, gradient surfaces or glass.
- **Container** capped at 1440px and centred. At wide viewports the gutters grow, not the grid.
- `overflow-x: clip` on `html` and `body`.
- **App shell:** a 64px top bar (wordmark, primary nav, search, notifications, avatar) and a left rail inside the learning views only (lesson outline). No sidebar dashboard on every page.

## 6. Components and states

Every interactive control defines **eight states**: default, hover, focus-visible, active, disabled, loading, error, success where relevant. `:focus-visible` shows instantly as a 2px `--color-focus` outline with 2px offset.

| Component | Spec |
|---|---|
| Primary button | Accent fill, `--color-accent-ink` text, 6px radius, 40px tall (36 compact), weight 500. One primary per view. |
| Secondary button | `--color-paper-2` with a 1px `--color-rule-2` outline, same size |
| Ghost / link | Ink text, `--color-rule-2` underline that turns accent on hover |
| Input | `--color-paper-3` inside a 1px rule, 6px radius, 40px. Error: `--color-bad` border plus a message below (never colour alone). |
| Chip | Carries state only (domain, difficulty, status). Label face inside a 1px rule at 4px radius, tinted by meaning. |
| Status | Icon plus text, never a bare coloured dot |
| Progress | 4px track on `--color-rule`, accent fill, mono percentage beside it |
| Tables | Hairline rows, label-face heads in sentence case, mono for numeric columns |
| Toasts | Bottom-right, `--color-paper-2`, auto-dismiss after 5s, pause on hover |
| Empty state | One line saying what's missing and one action that fixes it |

### Roadmap node states (Module D)

| State | Fill | Border | Icon | Text |
|---|---|---|---|---|
| Locked | `paper-3` | `rule` dashed | lock | muted, with the prerequisite named in the tooltip |
| Available | `paper-2` | `rule-2` | circle | ink |
| In progress | `accent-soft` | `accent` | half-ring with the mono percentage | ink |
| Completed | `accent` | `accent` | check | accent-ink |
| Review (recommended to skip) | `paper-2` | `attn` | bookmark | ink + "Review" chip |

Edges use `--color-rule-2` at 1.5px. An edge into a completed node becomes accent. The current node carries a static 2px accent ring with no pulse. Below 768px, the graph collapses to an outline list with the same states and data.

### Charts (Module H, D32)

Charts come from `frontend/src/lib/charts` (`ColumnChart`, `LineChart`, `ChartTable`; pure scales in `scale.ts`). One series, one colour: the accent. Columns are at most 24px wide with a 4px rounded data end and a square foot on the baseline; lines are 2px over a 10% accent wash, with a dot and the value at the last point. Gridlines and axes are solid 1px hairlines in `rule`; a reference such as the weekly goal is a solid `ink-2` hairline with its own label. Text is never in the series colour. Each chart is one tab stop: arrow keys, Home and End move a readout that is also announced, and pointer hover shows the same readout. Every chart has a "Show as a table" disclosure, so no value depends on hovering or on colour. Charts draw at their measured pixel width (no viewBox stretching) and sit in panels that already follow container width. Durations tick in minutes, then hours ("1.5 h"). A chart appears only when it has something to compare: one assessment is a number, two or more are a line.

### Exercise feedback (Module F)

After submission, each question shows **Correct** or **Incorrect** with an icon and text, the learner's answer (struck through if wrong, on `bad-soft`), the correct answer on `ok-soft`, the explanation, and a "Review: *Section name*" link that scrolls the theory panel to the referenced section. Nothing is revealed before submission.

## 7. Iconography and imagery

- **Lucide** at 16px or 20px with 1.5px stroke, in `currentColor`. Never emoji as icons.
- **Course thumbnails:** generated from data, not stock art. Each domain has a typographic thumbnail: the domain glyph, the course title in the display face, and a mono "domain / level" line, on `paper-3` with a hairline. They stay consistent and honest until real artwork exists.
- **Diagrams** (OSI, handshake, topology) are React components drawn in SVG with tokens, so they follow the theme and are labeled for screen readers.
- **3D** only where spatial structure is the point (network topology, packet traversal), lazy-loaded with a static SVG fallback.

## 8. Writing in the UI

- Sentence case everywhere. No tracked caps labels.
- Numbers carry units: `42 min`, `68%`, `3 of 7 lessons`.
- No invented social proof on marketing pages: no user counts, testimonials or partner logos unless they're real and supplied.
- Meta lines are separate elements, not middle-dot chains.
- All strings live in `i18n/messages/{en,vi}.json`.

## 9. Landing page direction (Module A)

The full Hallmark Picks block (shape draw, hero, sections, sweep) is produced when the landing page is built in Phase 3, because it needs real course and career data. Direction fixed now:

- **Job:** sign up, then take the assessment.
- **Signature:** an assessment result turning into a roadmap in one panel. Domain scores on the left in mono. On the right, the roadmap graph rearranges: gap topics become required (amber reason), strengths become "review". It's built from the real components and the real seed data.
- **Hero:** headline of seven words or fewer, one sentence, a primary "Start the assessment" action and a secondary "Browse careers". All of it fits within 1280×800.
- **No** stock photography, logo walls, fake metrics or gradient blobs.

## 10. Enforcement

- Tokens are the only source of colour and font. ESLint and stylelint rules reject hex or rgb literals and `font-family` outside `tokens.css`.
- No `transition: all`. Only `transform` and `opacity` animate.
- Hallmark `sloplint --render` runs on the landing and key app screens at the end of each UI phase, and its findings are fixed before the phase closes.
