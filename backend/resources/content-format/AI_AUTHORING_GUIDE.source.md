# CyberCompass AI authoring guide

You are writing learning content for **CyberCompass**, a platform that teaches cybersecurity from the ground up: networking, operating systems, Linux, web security, cryptography, hardening and security fundamentals. Learners are beginners who are smart but new; many study in English as a second language. An administrator gives you this guide, a **course overview** exported from the platform, and a request. You answer with a **content package**: one JSON document the administrator uploads. It is checked by the platform, shown as a preview, and lands in a **draft**. Nothing you write reaches learners until a person reviews and publishes it.

This guide is complete on its own: you do not need to browse anything else.

## 1. How to work

1. **Read the course overview first.** It lists every lesson, every section (with its `key`, `uuid`, type, heading and a gist of its text), each lesson's objectives with a hint of which sections serve them, the block types each lesson uses, the course map, the terms already explained, and related courses.
2. **Do not repeat or contradict what exists.** If a related course or another lesson already teaches something, refer to it in a sentence ("see the lesson *ARP* in this course") instead of teaching it again. Use the terms already explained the same way the lessons do.
3. **Fill real gaps.** Objectives marked **no dedicated section found** are the first candidates.
4. **Name sections correctly.**
   - To change, move after or remove an **existing** section, name it by its **`uuid`**, copied exactly from the overview. You may also give its `key`; the platform refuses the operation if the two name different sections.
   - Give a **`key` only to a new section.** A key is lowercase letters, digits and single hyphens, and must not be used by any other section of the lesson. Make it the slug of the heading (`"heading": "Devices by layer"` → `"key": "devices-by-layer"`), because the platform derives keys from headings: a key that does not match its heading is shown differently in the next overview. A new section without a heading (such as a recap) needs no key.
   - Never invent a `uuid`. A new section has none: the platform gives it one.
5. **Send back the fingerprint.** Copy the lesson's fingerprint from the overview into `base.lesson_fingerprint`. If the lesson changed since the overview was exported, the administrator is warned.
6. **Answer with the package only**: one JSON document, no commentary before or after it, unless the administrator asked for a zip bundle (section 5).

## 2. The package

Every package is a JSON object with this envelope:

```json
{
  "format": "cybercompass.content",
  "format_version": 1,
  "kind": "lesson-sections",
  "generator": "the chatbot's name and the date, e.g. Claude, 2026-11-24",
  "target": { "lesson": "osi-model" },
  "base": { "lesson_fingerprint": "fb8827a5153c6918" },
  "operations": []
}
```

`kind` is one of:

| kind | What it does | Body |
|---|---|---|
| `lesson-sections` | Adds, changes, moves or removes sections of one existing lesson | `target.lesson` (slug), `operations` |
| `lesson` | A whole lesson: a new one, or every section and field of an existing one | `target.course`, `target.chapter` (for a new lesson: the chapter's exact title or its number), `document` |
| `post-sections` | Like `lesson-sections`, for a blog post | `target.post` (slug), `operations` |
| `post` | A whole blog post | `document` |
| `course` | Several lessons at once, only in a zip bundle | `target.course`, `documents: [{ "chapter", "document" }]` |

### Sections

Every section has the same shape, whatever its block type:

```json
{
  "key": "devices-by-layer",
  "type": "comparison",
  "heading": "Devices by layer",
  "payload": { },
  "sources": [ { "label": "RFC 1122: Requirements for Internet Hosts", "url": "https://www.rfc-editor.org/rfc/rfc1122", "kind": "rfc" } ]
}
```

- `type` is a block type from the **block reference** at the end of this guide; `payload` must match that type's schema exactly (no extra fields).
- `heading` is optional but nearly always wanted: it becomes the lesson's "On this page" entry. Callouts use it as their title.
- `sources` is optional; section 3 says when it is required.

### Operations (`lesson-sections`, `post-sections`)

Applied in order. Each names an existing section by `uuid` (and optionally `key`), except `append`.

| op | Fields | Effect |
|---|---|---|
| `append` | `section` | Adds the section at the end |
| `insert_after` | `uuid` (or `key`), `section` | Adds the section right after the named one |
| `insert_before` | `uuid` (or `key`), `section` | Adds the section right before the named one |
| `replace` | `uuid` (or `key`), `section` | Replaces the named section's content; it keeps its identity, so links and exercises keep working |
| `remove` | `uuid` (or `key`) | Removes the named section |

A section added earlier in the same package can be named by its new `key` in a later operation. The platform refuses to remove a section that a graded exercise question or the course map points at.

### A whole lesson (`lesson`)

```json
{
  "format": "cybercompass.content",
  "format_version": 1,
  "kind": "lesson",
  "generator": "…",
  "target": { "course": "networking-fundamentals", "chapter": "How networks are built" },
  "document": {
    "slug": "osi-model-in-practice",
    "title": "The OSI model in practice",
    "minutes": 15,
    "summary": "One or two sentences, at most 300 characters.",
    "objectives": ["Up to 10 objectives, each a sentence"],
    "takeaways": ["Up to 10 key takeaways"],
    "resources": [{ "label": "Further reading", "url": "https://…" }],
    "sections": []
  }
}
```

A new lesson is created as a draft in that chapter. It joins no roadmap topic and is not placed on the course map: the administrator does that. For an existing lesson (same slug), sections are matched by `uuid` or `key` and keep their identity; existing sections you leave out are removed.

A whole post (`post`) has `slug`, `title`, `summary`, `tags` (up to 8, lowercase with hyphens), `cover` (an image, or null) and `sections`.

## 3. Content quality rules

### Sources for facts

Every section that states facts must list at least one source in `sources`. The block reference says for each type whether it **states facts**: always (text, comparisons, timelines, step-throughs and others), never (knowledge checks, code, images, calculators, 3D), or depending on a field. A callout states facts when its tone is `info`, `warning` or `fun-fact`; a `tip` or a `recap` does not. For a fun fact that is only an analogy, the platform's "no sources" warning can be ignored; an anecdote needs a real source.

Each source is `{ "label", "url", "kind", "note"? }`, where `kind` is one of `paper`, `rfc`, `docs`, `article`, `book`, `community`, `other`.

- **Prefer primary sources:** the RFC or standard, the vendor's or project's official documentation, a security team's own write-up. A blog post that summarises an RFC is worse than the RFC.
- **Community threads** (Stack Overflow, Server Fault, Reddit, mailing lists) are acceptable for practical detail, labelled `"kind": "community"`.
- **Never invent a citation, a URL, an author, a title or a section number.** Cite only sources you are confident exist at that address. If you are not sure, write the source you believe in with `"kind": "other"` and `"note": "needs verification"`, so the administrator checks it. A missing source is better than an invented one.
- Use `https` links.

Good:

```json
"sources": [
  { "label": "RFC 826: An Ethernet Address Resolution Protocol", "url": "https://www.rfc-editor.org/rfc/rfc826", "kind": "rfc" },
  { "label": "Server Fault: why does ARP use broadcast?", "url": "https://serverfault.com/questions/…", "kind": "community", "note": "needs verification" }
]
```

Bad: a made-up paper title, a URL you have not seen, a section number you guessed, or a blog cited for a fact the RFC states.

### "In other words": a recap after dense blocks

After a block that is long or dense, add a short recap that says the same thing **another way**: a `callout` with `"tone": "recap"`. Add one when the block before it:

- is prose of more than about 250 words,
- is a step-through or steps block with more than 6 steps, or
- introduces three or more new terms.

The recap is 2 to 4 sentences and uses **one** angle: an analogy, why it matters, the attacker's view, the defender's view, or a one-line mental model. It must not repeat the block's first sentences. It needs no heading (the platform labels it "In other words"); if you give one, keep it short.

Good: `{ "type": "callout", "heading": null, "payload": { "tone": "recap", "text": "Think of each layer as a courier who only reads the label meant for them: the next courier never opens the envelope inside." } }`

Bad: a recap that restates the first sentence, or one after a short block.

### Fun facts: hooks that help learners remember

A `callout` with `"tone": "fun-fact"` holds one memorable association: an analogy (a CPU pipeline is like an assembly line), a surprising comparison, or a short story about how something came to be.

- At most one or two per lesson, where they help memory. Never in the middle of a procedure.
- **An analogy needs no source. An anecdote does:** "the inventor got the idea from…" is a factual claim and must carry a real source in `sources`.
- If a story is a popular legend rather than established fact, say so in the text ("the often-told story is…").

## 4. Writing rules

These are the platform's own authoring rules, the same ones its human authors follow.

<!-- include: CONTENT_AUTHORING_GUIDE §1 -->
### 1. Principles

1. **Text first.** Every lesson must make sense with its visuals ignored. Visuals explain; they never carry information that exists nowhere else.
2. **A visual earns its place.** Add one only when it shows something prose can't do as well: structure (layers, trees), order (sequences, steps), space (topologies, 3D), or something to try (calculators, questions). Never use one as decoration.
3. **One idea per block.** Two short blocks are better than one dense block.
4. **Plain words.** Write for a beginner who is smart but new. Define a term the first time you use it, in bold. Prefer short sentences and concrete examples: an unpatched VPN appliance, not "a vulnerable asset".
5. **Security angle.** Each lesson should answer "why does this matter for security?": how an attacker abuses it, or how a defender uses it.
6. **Accurate.** Check protocol details, numbers and identifiers (ports, RFCs, ATT&CK IDs) against a primary source, and link that source in Further reading. Use documentation addresses (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`, `example.com`) instead of real hosts.

Learning content is written in English. The interface around it is translated (English and Vietnamese).
<!-- end include -->
<!-- include: CONTENT_AUTHORING_GUIDE §2 -->
### 2. Choosing a block

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
<!-- end include -->
<!-- include: CONTENT_AUTHORING_GUIDE §3 -->
### 3. Writing each block

Limits are enforced when you save; the editor shows them in each block's **Field reference** and flags problems as you type. Every diagram block shows a **live preview** using exactly what learners will see.

#### Text, callouts and steps

- Text format: blank line between paragraphs; `- ` for bullets; `1. ` for numbered lists; `**bold**`; `` `code` ``; `[link text](https://…)`. Only `https://` links are accepted, and they open in a new tab. No HTML: it is shown as text.
- Callout tones: **info** for context, **tip** for a memory aid or shortcut, **warning** for an attack or a common mistake, **recap** ("In other words") right after a long or dense block to say the same thing another way (an analogy, why it matters, the attacker's or defender's view; 2 to 4 sentences, never a copy of its opening), and **fun-fact** for a memorable association (an analogy needs no source; an anecdote or "the inventor got the idea from…" story is a factual claim and needs one, and a popular legend is called a legend). At most one or two of each per lesson, never a fun fact in the middle of a procedure.
- **Sources** (D68): any section can list what backs its facts (label, link, kind: paper, rfc, docs, article, book, community, other, and an optional note), shown to readers in a collapsed "Sources" list under the section. Prefer primary sources (RFCs, official docs); label forum threads as community; never invent one, and write "needs verification" in the note when unsure. In the editor it is the Sources list on each block; in content files a named `'sources' => [...]` element on the section. The lesson's `resources` list stays as further reading.
- Headings become the lesson's "On this page" list, so make them short and specific ("TCP and UDP", not "More details").

#### Layer stack

Layers top to bottom in display order (OSI: 7 first). Give each a `tag` (the number), a `name`, an optional `meta` shown on its tab (the data unit), a one or two sentence `description`, optional `tags` (examples, labelled by `tags_label`), optional `facts`, and an optional `note` (warning tone works well for "attack at this layer").

#### Sequence

2 to 5 `actors` with short ids and names, then the `messages` in order. Keep `label` to one word (`SYN`, `Query`) and `detail` very short (`seq=100`, `A?`): message text must fit between neighbouring actors, so long details overlap other lines with four or five actors. Put the explanation in `explanation`. `states` (optional) shows each actor's state after the message, such as TCP states.

#### Flow and tree

- **Flow:** `nodes` with `kind` start, step, decision or end, and `edges` with optional short labels ("Yes", "No"). Loops are allowed: an edge can go back. Layout is automatic; set `direction` to `right` for short wide flows.
- **Tree:** each node names its `parent`; exactly one node has none. `note` is a small second line (an ID, a type). Children appear in the order you list them.
- Any node with a `detail` can be selected by the learner. Use details for the "why", and keep labels to a few words.
- Both get an automatic text version for screen readers.

#### Network topology

Devices are placed by `x` and `y` as percentages (0 to 100) of the canvas; `aspect` picks its shape (`4:3` gives more height for three zones). Zones are rectangles in the same units, coloured by `tone`: trusted, untrusted, dmz or neutral.

- A zone's drawn box includes room for its devices' icons and labels, so **leave about 18% between zones vertically** and keep devices at least 8% inside a zone's edges.
- Device `kind` picks the icon: router, switch, firewall, server, database, workstation, laptop, phone, access-point, cloud, internet, attacker.
- Put long explanations in `detail` (selectable), not in `address`, which is drawn under the label.
- Check the preview at phone width too: wide topologies scroll inside their own box.

#### Comparison, timeline, bit field

- **Comparison:** 2 to 4 columns, one value per column in every row. Keep each cell under about 15 words; on phones the table becomes one card per column.
- **Timeline:** events in order; `when` is free text (`09:02`, `1983`).
- **Bit field:** rows of fields, each field's bits written as 0s and 1s, at most 64 bits per row. Use `tone` to tell network from host bits, and `show_decimal` when each field is a meaningful number (octets).

#### Calculator

Pick the `tool` (`cidr`, `chmod` or `password-entropy`) and an optional starting value that shows the case you just explained. Everything runs in the learner's browser; nothing typed is sent or saved. Never put a real password in a password example.

#### Step-through

2 to 20 steps, each with a `title`, `text`, optional `parts` (a row of labelled boxes, such as headers wrapping data) and optional `state` (label and value pairs). Use **the same state labels in every step**: values that change from the previous step are highlighted, which is the point of the block.

#### Knowledge check

1 to 3 questions per block, ideally at the end of each lesson. They are **practice**: never graded, never saved, and they don't affect lesson completion. Answers are sent with the lesson, so never reuse these questions in graded assessments.

- Ask about applying the idea ("a server receives thousands of SYNs…"), not recalling a sentence.
- Give 3 or 4 options. Wrong options should be mistakes a learner might really make.
- Tick every correct option. More than one correct option makes it a "choose all that apply" question, which the learner is told.
- Always write an `explanation` saying *why* the answer is right. It is shown whether the learner is right or wrong.

#### 3D scene and 3D script

Use 3D when space or movement is the lesson: a packet crossing devices, layers around an asset. Read [SANDBOXED_3D.md](SANDBOXED_3D.md) for how it is isolated.

- Start with a **3D scene** (shapes, labels, links with moving packets, animations, click-to-explain, guided steps). Use a **3D script** only when a scene truly can't express it; scripts can be switched off by the operator (`CONTENT_SCRIPTED_3D=false`).
- The **text description is required** and must stand on its own: it is what screen-reader users get, and what everyone gets on devices that can't run 3D. Describe what is shown and what the steps explain.
- Frame the camera so all objects fill most of the view at the start; the preview shows the result. Check each guided step's camera.
- Budgets: up to 150 objects and links, 8 lights, 20 steps. Fewer is better: the pilot scenes use under ten objects each.
- Label objects with `text`; put explanations in `info` (click to read) and in guided steps.
<!-- end include -->
<!-- include: CONTENT_AUTHORING_GUIDE §5 -->
### 5. Before you publish

- [ ] The lesson reads well with every visual skipped.
- [ ] Each visual shows something the text alone doesn't.
- [ ] Every 3D block has a complete text description; every image has alt text.
- [ ] Facts, numbers and IDs are checked against a primary source, linked in Further reading.
- [ ] Examples use documentation addresses and domains.
- [ ] The lesson ends with a knowledge check whose explanations teach.
- [ ] Preview checked at desktop and phone width, in light and dark themes.
- [ ] Objectives, summary and key takeaways match what the lesson actually teaches.
<!-- end include -->

## 5. Long text, code and images: zip bundles

When the package has images, or long prose and code that are hard to escape inside JSON, the administrator may ask for a **zip bundle** (`.ccpkg.zip`) instead:

- `manifest.json` at the root holds the envelope.
- Anywhere in it, `{ "$file": "texts/why-layers.md" }` is replaced by the text of that file (UTF-8). A `.json` file is read as a JSON value instead, which lets a course bundle keep one file per lesson: `{ "chapter": 2, "document": { "$file": "lessons/arp.json" } }`.
- An image is `{ "file": "media/osi-layers.png", "alt": "What the image shows" }` wherever a block expects an image (a `media` block's `media`, a post's `cover`). PNG, JPEG, WebP or GIF; alt text is required.
- Paths are relative, use forward slashes and stay inside the bundle.

Without a zip, write everything inline in the JSON.

## 6. JSON pitfalls

- Escape every double quote inside a string as `\"` and every backslash as `\\` (Windows paths, regular expressions).
- A line break inside a string is `\n`; a real line break is invalid JSON. Code blocks and multi-paragraph prose need `\n` between lines, and `\n\n` between paragraphs.
- No trailing commas, no comments, no single quotes.
- Markdown in text is limited: `**bold**`, `` `code` ``, `[label](https://…)`, `- ` bullets, `1. ` lists. No HTML.
- Use plain characters: straight quotes in code, real Unicode in prose (é, ü) rather than HTML entities.

<!-- generated: limits -->

## 8. Before you answer

- [ ] One JSON document with `format`, `format_version` 1, `kind`, `generator`, `target` and, for an existing lesson, `base.lesson_fingerprint`.
- [ ] Existing sections named by `uuid` copied from the overview; new sections have a `key` (the heading as a slug) and no `uuid`.
- [ ] Every `type` is in the block reference, and every `payload` matches its schema with no extra fields.
- [ ] Every fact-stating section has a real source; uncertain sources say "needs verification"; nothing is invented.
- [ ] A recap after each long or dense block; at most one or two fun facts; anecdotes sourced, legends called legends.
- [ ] Nothing repeats another lesson or a related course; terms already explained are used, not re-explained.
- [ ] Documentation addresses (`192.0.2.0/24`, `198.51.100.0/24`, `203.0.113.0/24`, `example.com`) instead of real hosts.
- [ ] The JSON parses: quotes and backslashes escaped, `\n` for line breaks, no trailing commas.

## 9. A prompt the administrator can use

> You are writing content for CyberCompass. Read the attached AI authoring guide and course overview. Then write a `lesson-sections` package for the lesson `<slug>` that <what is wanted>. Fill the objectives marked "no dedicated section found" first. Name existing sections by their uuid from the overview, and give a key only to new sections. Cite real sources for every fact and mark anything uncertain "needs verification". Add a recap after any dense block and at most one fun fact. Answer with the JSON package only.

<!-- generated: block-reference -->
