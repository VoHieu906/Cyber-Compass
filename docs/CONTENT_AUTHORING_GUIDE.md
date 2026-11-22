# Content authoring guide

How to write CyberCompass lessons: choosing blocks, writing them well, and getting them live. It is for admins writing in the console at `/admin/lessons`, and for developers maintaining the content files in `backend/database/seeders/content`.

The two pilot courses, **Networking Fundamentals** and **Security Fundamentals**, use every block type described here. When in doubt, open one of their lessons in the editor and copy what works.

## 1. Principles

1. **Text first.** Every lesson must make sense with its visuals ignored. Visuals explain; they never carry information that exists nowhere else.
2. **A visual earns its place.** Add one only when it shows something prose can't do as well: structure (layers, trees), order (sequences, steps), space (topologies, 3D), or something to try (calculators, questions). Never use one as decoration.
3. **One idea per block.** Two short blocks are better than one dense block.
4. **Plain words.** Write for a beginner who is smart but new. Define a term the first time you use it, in bold. Prefer short sentences and concrete examples: an unpatched VPN appliance, not "a vulnerable asset".
5. **Security angle.** Each lesson should answer "why does this matter for security?": how an attacker abuses it, or how a defender uses it.
6. **Accurate.** Check protocol details, numbers and identifiers (ports, RFCs, ATT&CK IDs) against a primary source, and link that source in Further reading. Use documentation addresses (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`, `example.com`) instead of real hosts.

Learning content is written in English. The interface around it is translated (English and Vietnamese).

## 2. Choosing a block

| You want to show… | Use | Pilot example |
|---|---|---|
| Explanation, lists, links | **Text** (`prose`) | Every lesson |
| A note, tip or warning beside the text | **Callout** | "Attackers use the handshake too" |
| A numbered procedure or worked calculation | **Steps** | "Working out a /26" |
| A command or script to copy | **Code** | "Try it yourself" (`dig`) |
| A screenshot | **Image** (`media`) | — |
| Ordered layers, each with details | **Layer stack** | The seven OSI layers; TCP/IP's four |
| Messages between parties over time | **Sequence** | TCP handshake, DNS resolution, DHCP |
| A process or decision | **Flow** | Choosing a risk response |
| A hierarchy | **Tree** | The DNS tree, how ATT&CK is organised |
| Devices and links in a network, with zones | **Network topology** | Segmentation in practice |
| Two to four things side by side | **Comparison** | TCP and UDP; vulnerability, threat, risk |
| Events in time order | **Timeline** | — |
| Bits and the fields they make | **Bit field** | An IPv4 address in binary; a /26 |
| Something the learner calculates themselves | **Calculator** | CIDR in "Subnetting" |
| A walkthrough where values change step by step | **Step-through** | Encapsulation; a risk score |
| Practice questions | **Knowledge check** | Every pilot lesson |
| Spatial structure or movement | **3D scene** | Follow a packet; layers around the data |
| Anything a 3D scene can't express | **3D script** | — |
| A one-off interactive diagram built in code | **Bespoke diagram** | The CIA triad |

Prefer the simplest block that works. A comparison beats a 3D scene for a list of differences; a sequence beats a flow when the point is *who sends what to whom*.

## 3. Writing each block

Limits are enforced when you save; the editor shows them in each block's **Field reference** and flags problems as you type. Every diagram block shows a **live preview** using exactly what learners will see.

### Text, callouts and steps

- Text format: blank line between paragraphs; `- ` for bullets; `1. ` for numbered lists; `**bold**`; `` `code` ``; `[link text](https://…)`. Only `https://` links are accepted, and they open in a new tab. No HTML: it is shown as text.
- Callout tones: **info** for context, **tip** for a memory aid or shortcut, **warning** for an attack or a common mistake. At most one or two per lesson, or they stop standing out.
- Headings become the lesson's "On this page" list, so make them short and specific ("TCP and UDP", not "More details").

### Layer stack

Layers top to bottom in display order (OSI: 7 first). Give each a `tag` (the number), a `name`, an optional `meta` shown on its tab (the data unit), a one or two sentence `description`, optional `tags` (examples, labelled by `tags_label`), optional `facts`, and an optional `note` (warning tone works well for "attack at this layer").

### Sequence

2 to 5 `actors` with short ids and names, then the `messages` in order. Keep `label` to one word (`SYN`, `Query`) and `detail` very short (`seq=100`, `A?`): message text must fit between neighbouring actors, so long details overlap other lines with four or five actors. Put the explanation in `explanation`. `states` (optional) shows each actor's state after the message, such as TCP states.

### Flow and tree

- **Flow:** `nodes` with `kind` start, step, decision or end, and `edges` with optional short labels ("Yes", "No"). Loops are allowed: an edge can go back. Layout is automatic; set `direction` to `right` for short wide flows.
- **Tree:** each node names its `parent`; exactly one node has none. `note` is a small second line (an ID, a type). Children appear in the order you list them.
- Any node with a `detail` can be selected by the learner. Use details for the "why", and keep labels to a few words.
- Both get an automatic text version for screen readers.

### Network topology

Devices are placed by `x` and `y` as percentages (0 to 100) of the canvas; `aspect` picks its shape (`4:3` gives more height for three zones). Zones are rectangles in the same units, coloured by `tone`: trusted, untrusted, dmz or neutral.

- A zone's drawn box includes room for its devices' icons and labels, so **leave about 18% between zones vertically** and keep devices at least 8% inside a zone's edges.
- Device `kind` picks the icon: router, switch, firewall, server, database, workstation, laptop, phone, access-point, cloud, internet, attacker.
- Put long explanations in `detail` (selectable), not in `address`, which is drawn under the label.
- Check the preview at phone width too: wide topologies scroll inside their own box.

### Comparison, timeline, bit field

- **Comparison:** 2 to 4 columns, one value per column in every row. Keep each cell under about 15 words; on phones the table becomes one card per column.
- **Timeline:** events in order; `when` is free text (`09:02`, `1983`).
- **Bit field:** rows of fields, each field's bits written as 0s and 1s, at most 64 bits per row. Use `tone` to tell network from host bits, and `show_decimal` when each field is a meaningful number (octets).

### Calculator

Pick the `tool` (`cidr`, `chmod` or `password-entropy`) and an optional starting value that shows the case you just explained. Everything runs in the learner's browser; nothing typed is sent or saved. Never put a real password in a password example.

### Step-through

2 to 20 steps, each with a `title`, `text`, optional `parts` (a row of labelled boxes, such as headers wrapping data) and optional `state` (label and value pairs). Use **the same state labels in every step**: values that change from the previous step are highlighted, which is the point of the block.

### Knowledge check

1 to 3 questions per block, ideally at the end of each lesson. They are **practice**: never graded, never saved, and they don't affect lesson completion. Answers are sent with the lesson, so never reuse these questions in graded assessments.

- Ask about applying the idea ("a server receives thousands of SYNs…"), not recalling a sentence.
- Give 3 or 4 options. Wrong options should be mistakes a learner might really make.
- Tick every correct option. More than one correct option makes it a "choose all that apply" question, which the learner is told.
- Always write an `explanation` saying *why* the answer is right. It is shown whether the learner is right or wrong.

### 3D scene and 3D script

Use 3D when space or movement is the lesson: a packet crossing devices, layers around an asset. Read [SANDBOXED_3D.md](SANDBOXED_3D.md) for how it is isolated.

- Start with a **3D scene** (shapes, labels, links with moving packets, animations, click-to-explain, guided steps). Use a **3D script** only when a scene truly can't express it; scripts can be switched off by the operator (`CONTENT_SCRIPTED_3D=false`).
- The **text description is required** and must stand on its own: it is what screen-reader users get, and what everyone gets on devices that can't run 3D. Describe what is shown and what the steps explain.
- Frame the camera so all objects fill most of the view at the start; the preview shows the result. Check each guided step's camera.
- Budgets: up to 150 objects and links, 8 lights, 20 steps. Fewer is better: the pilot scenes use under ten objects each.
- Label objects with `text`; put explanations in `info` (click to read) and in guided steps.

## 4. Getting content live

### In the admin console (most changes)

1. **Lessons → New lesson**, or open an existing one. You always edit a **draft**; learners keep seeing the published version.
2. Add blocks from the grouped menu. Each new block starts from a working example: edit it rather than starting from empty.
3. **Save draft** (Ctrl+S) often. Problems are shown next to the field they concern.
4. **Preview** shows the draft exactly as learners will see it.
5. **Publish** with a short note. Every publish is a revision; **History** can restore any earlier one into the draft.

If someone else saved the lesson after you opened it, your save is refused rather than overwriting their work. Load the latest version and reapply your change.

### In content files (seed content, reviewed in git)

Courses live in `backend/database/seeders/content/courses/<course>.php`. Blocks are written as `[type, heading, payload]`; the five original types also accept the compact forms used throughout the files (for example `['prose', 'Heading', <<<'MD' … MD]`).

```bash
docker compose exec api php artisan content:import            # validate and load the files
docker compose exec -u root api php artisan content:export networking-fundamentals   # write the live course back to its file
```

- **Import** validates everything with the same rules as the admin console and writes nothing if any block is invalid.
- **Once a lesson is edited in the admin console, the database owns it**: imports skip it (unless `--force`, which asks first), so curated work is never overwritten.
- **Export** writes a course's *published* lessons back to its file so the admin's work can be reviewed and committed. Drafts are not exported. Review the diff before committing; payload keys may be reordered, but the content is identical, and importing the export changes nothing.
- **Section identity.** Each block has a stable UUID that future exercises will link to. In files, it is derived from the lesson slug and the block's heading, so **renaming a heading in a file gives the block a new identity**. Blocks created in the admin console keep theirs through an explicit `'uuid'` that export adds. Don't edit or copy those values.

## 4a. Exercises (graded, Phase 5)

Exercises are graded practice at the end of a lesson; passing one completes the lesson (ARCHITECTURE D29 to D31). They live in `backend/database/seeders/content/exercises/<course>.php`, keyed by lesson slug, and are loaded by `ExerciseSeeder` after the lessons (it runs inside `db:seed` and `content:import`'s seeder; on its own: `php artisan db:seed --class=ExerciseSeeder`).

- **Format.** Each question has a stable `key`, a `type`, a `prompt`, an `explanation` and the fields of its type (see the header of `networking-fundamentals.php` and `Learning\Services\QuestionTypes`). Write them in the order learners should meet them.
- **Theory reference, required.** `'section' => 'encapsulation'` names the block that teaches the question by its key (the heading as a slug). Admin-made blocks are named by `'section_uuid'`. The seed fails if the block does not exist in that lesson.
- **Never reuse knowledge checks.** Ungraded `knowledge-check` blocks ship their answers with the lesson; exercise questions are new questions whose keys stay on the server.
- **Matching and ordering** are shuffled for display automatically and never shown already in the right order; write the items in the correct order or pairing.
- **Removing a question** archives it; past attempts keep it. Changing a question's type after someone answered it is refused: give the changed question a new key.
- **Blocks that questions use cannot be deleted.** Publishing a draft or importing a file that drops such a block is refused with a message naming the questions (D30), and the editor marks those blocks and warns before removing one. Renaming a block's heading in a file counts as removing it (its identity is derived from the heading), so update the exercise file first.

## 4b. Labs (Phase 5)

Labs live in `backend/database/seeders/content/labs.php`, keyed by slug in the order offered, and are loaded by `LabSeeder` after the lessons (`php artisan db:seed --class=LabSeeder` on its own). Each lab has a title, summary, markdown `briefing`, domain, optional `lesson` slug (the lesson then links to it), `engine`, difficulty, minutes and a `scenario`.

- **Engines and scenarios.** `linux-shell`: hostname, user, home, cwd, `files` (absolute path => text, at most 128 KB) and optional extra `dirs`. `firewall-rules`: server, starting `default` and `rules`. `http-inspector`: app, the learner's `session` cookie, `captured` exchanges, `routes` (each with an id, method, path, optional required cookie and a response) and `not_found`. Formats: the class header of each engine in `app/Modules/Labs/Engines`.
- **Checkpoints.** Every lab lists `checkpoints` with a stable `id`, a `title` (the task as the learner reads it) and an optional `hint`. Kinds: `answer` (accepted `answers`, compared ignoring case and spacing) for every engine; `read` {path} for the shell; `traffic` {packets with `expect`} for the firewall; `request` {route} for the HTTP inspector.
- **Kept on the server.** File contents, answers, traffic packets and routes never reach the browser; the learner discovers them through the engine. Do not put an answer in a title, hint or briefing.
- **Write a story, not a quiz.** Each lab should be a small, believable situation that the lesson's skills solve, with checkpoints in the order an investigator would reach them. Use documentation addresses (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`) and `.example` domains.
- **Removing a lab** archives it; attempts keep it.

## 4c. Blog posts (F2)

A **post** is a long read outside the courses: an explainer, a deep dive, a story about how a system really works. It uses the same blocks, rules and editor as a lesson (ARCHITECTURE D55), but it never counts toward progress, streaks or roadmaps, and guests can read it.

- **Reference post.** "Docker & Containers 101" (`content/posts/docker-containers-101.php`) uses every block type in a long read, with images from files; copy what works from it.
- **Post or lesson?** Write a **lesson** when the material is part of a path someone follows to a goal: it sits in a chapter, has objectives and takeaways, and may get an exercise or a lab. Write a **post** when it stands alone, crosses several topics or goes deeper than a learner needs to progress (for example "Docker & Containers 101"). A post can link to the lessons and labs it builds on; a lesson never depends on a post.
- **Length.** Up to 150 blocks. Long posts read well when every major part has a heading (the reader's "On this page" list, the compass and the heading links are built from headings), with a knowledge check after each key part rather than a quiz at the end. Callout headings are not listed.
- **Details.** A summary (the card, search results and the page description; required to publish), up to 8 tags (lowercase, hyphenated), an optional cover image. The reading time is computed from the text (200 words a minute, half a minute per interactive block); there is nothing to type.
- **In the admin console.** Admin → Blog posts → New post, then the same Save draft, Preview, Publish with a note and History as lessons. A published post can be archived (it leaves the blog and search) and brought back as it was; only a post that was never published can be deleted.
- **In content files.** `backend/database/seeders/content/posts/<slug>.php` returns one post: `slug`, `title`, `summary`, `tags`, `published_at`, optional `cover`, and `sections` in the same `[type, heading, payload]` format as lessons. `content:import` loads it with the lesson rules: everything validated first, a post edited in the admin console is skipped unless `--force`, section identities come from the post slug and the heading (renaming a heading gives a block a new identity), and a post removed from the files is archived. `content:export <slug> --post` writes the live post back. `cybercompass:release` imports posts like everything else.
- **Images from files** (D56). Where a `media` block or a cover expects an uploaded image, a file can write `['file' => 'posts/media/<slug>/<name>.png', 'alt' => 'What the image shows']`, a path under `database/seeders/content`. The import re-encodes it to WebP through the upload pipeline (PNG, JPEG, WebP or GIF; SVG is refused like uploads, so render SVG artwork to PNG first), names it after its path and bytes (an unchanged file is reused, a changed one becomes a new image) and export writes the reference back. Only original artwork: no logos or copied images.

## 5. Before you publish

- [ ] The lesson reads well with every visual skipped.
- [ ] Each visual shows something the text alone doesn't.
- [ ] Every 3D block has a complete text description; every image has alt text.
- [ ] Facts, numbers and IDs are checked against a primary source, linked in Further reading.
- [ ] Examples use documentation addresses and domains.
- [ ] The lesson ends with a knowledge check whose explanations teach.
- [ ] Preview checked at desktop and phone width, in light and dark themes.
- [ ] Objectives, summary and key takeaways match what the lesson actually teaches.
