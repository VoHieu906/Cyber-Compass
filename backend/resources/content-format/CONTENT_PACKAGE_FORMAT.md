# Content package format, version 1

The format for moving lessons, blog posts and blocks into and out of the CyberCompass admin console (F6, ARCHITECTURE D67 to D71). It is written for the people who maintain the platform; chatbots read the [AI authoring guide](AI_AUTHORING_GUIDE.md), which covers the same format with writing rules and the generated block reference.

## Files

| File | Use |
|---|---|
| `*.ccpkg.json` | One UTF-8 JSON document: the envelope below. What a chatbot normally produces. At most 10 MB. |
| `*.ccpkg.zip` | `manifest.json` (the envelope) at the root, plus text, JSON and image files it names. For images, long texts and whole courses. At most 250 MB compressed. |

In a zip, `{"$file": "path"}` anywhere in the manifest is replaced by that file: a parsed JSON value for `.json` (which may name further files, three levels deep), otherwise its UTF-8 text with line endings normalised. Images are `{"file": "media/x.png", "alt": "..."}` wherever a block expects a media id, and a post's `cover`; they go through the upload pipeline (WebP on the uploads disk) when the package is applied. Outside a zip both forms are refused.

Every limit (sizes, entry count, compression ratio, JSON depth, extensions) is in `config('cybercompass.content_packages')` and checked before anything is unpacked or decoded (`PackageReader`).

## Envelope

| Field | Required | Meaning |
|---|---|---|
| `format` | yes | `"cybercompass.content"` |
| `format_version` | yes | `1`. A newer version is refused with a message naming the version this app reads. |
| `kind` | yes | `lesson`, `lesson-sections`, `post`, `post-sections` or `course` (zip only) |
| `generator` | no | Free text, kept in the audit log |
| `target` | per kind | `lesson` / `post` (slug) for the `-sections` kinds; `course` and `chapter` (exact title or 1-based number) for a new lesson and for `course` |
| `base` | no | `lesson_fingerprint` or `post_fingerprint` from the overview or an export; a mismatch is a warning, and changes how a missing key is explained |
| `document` | `lesson`, `post` | The whole document |
| `documents` | `course` | `[{"chapter", "document"}]`, at most 60 |
| `operations` | `-sections` kinds | At most 200, applied in order |

## Sections and identity

A section is `{key, type, heading, payload, sources}` in the stored shape; `payload` is validated by the block's JSON Schema through `BlockRegistry`, `sources` by `Documents/schemas/sources.json`. Nothing in the package code knows a block type.

- **Keys are not stored.** A section's key is its heading as a slug (`ContentImporter::sectionKeys`: explicit key in content files, else the heading slug, else `type-n`, with `-2` for repeats), so it changes when the heading does. The planner warns when a package key will read back differently.
- **UUIDs are stable.** An operation or a whole-document section names an existing section by `uuid`, `key` or both; both naming different sections is refused. A matched section keeps its UUID. A new section gets `sectionUuid(slug, key)` (`postSectionUuid` for posts), or a random UUID if that one is already used in the document. A UUID in a package is accepted only if it already belongs to the same document.
- **References are protected.** Removing a section that an active exercise question (D30) or the lesson's architecture (D57) names is refused.

Operations: `append {section}`, `insert_after {uuid|key, section}`, `insert_before {uuid|key, section}`, `replace {uuid|key, section}`, `remove {uuid|key}`.

## What an import does

1. The package is read (`PackageReader`) and planned (`PackagePlanner`): every error by its place in the package (at most 100 reported, the rest counted), warnings (stale fingerprint, factual blocks without sources, plain http sources, keys that will read back differently, a content-file lesson becoming database-owned, a new lesson on no topic and off the course map, a draft the package applies on top of), and the section changes per document.
2. Applying (`ImportPackage`) plans again in one transaction, refuses when a draft changed since the preview, stores the images, creates new lessons and posts as drafts, writes every draft through the working copy (the same validation as an editor save) and records one audit entry per document. Nothing is published.

In the admin console uploads go in parts (presigned S3 multipart in production, through the API locally), and validating, applying and zip exports run on the queue worker (`content_transfers`, D70). From the command line: `content:package validate|import|export`.

## Exports

- A lesson or post as `.ccpkg.json`: the working copy, with keys (and, for chosen sections, `replace` operations naming key and uuid) and `base` fingerprint. Images stay media UUIDs, which import back on the same installation.
- A lesson, post or course as `.ccpkg.zip`: the same, with every image it names under `media/` as `{file, alt}`, so it imports on another installation.
- The **course overview** (Markdown or JSON) for chatbots: see the [example](examples/networking-fundamentals.overview.md).

## Changing the format

Add a block type through the registry (schema, class, zod mirror, fixtures, renderer): packages, the round-trip test and the generated guide pick it up. Change the envelope only with a new `format_version`, keeping version 1 readable.
