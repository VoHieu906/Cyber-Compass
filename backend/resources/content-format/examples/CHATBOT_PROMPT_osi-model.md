# Prompt: fill the gaps in "The OSI model"

The exact prompt an administrator sends to a chatbot, with two attachments: `AI_AUTHORING_GUIDE.md` ("Download AI guide" on Admin → Courses) and `networking-fundamentals.overview.md` ("Export course overview"). The package it should produce looks like [osi-model.additions.ccpkg.json](osi-model.additions.ccpkg.json).

---

You are writing learning content for CyberCompass. Two files are attached:

- `AI_AUTHORING_GUIDE.md`: the package format, the writing rules and the block reference. Follow it exactly.
- `networking-fundamentals.overview.md`: the course as it is now: every lesson, every section with its key and uuid, the objectives with the sections that serve them, the course map and the terms already explained.

**Task.** Write one `lesson-sections` package for the lesson `osi-model` ("The OSI model", chapter "How networks are built") that fills the two objectives the overview marks **no dedicated section found**:

1. "Place common protocols and devices on the right layer": a compact reference that puts typical protocols and devices on each of the seven layers. The layer stack (`the-seven-layers`) already lists example protocols per layer as tags; do not repeat its descriptions, and say which layers have no devices of their own.
2. "Use the model to reason about where an attack happens": a short method for placing an attack on a layer (what does the attacker tamper with?), then example attacks with a defence at the same layer, one per layer.

**Do not repeat other lessons.** How each device works belongs to `network-devices-and-media`; the four-layer model, the handshake and SYN floods to `tcp-ip-model`; headers and PDUs to `encapsulation-and-pdus`; ARP and ARP spoofing to `arp`; TCP internals to `tcp-and-udp-in-depth`; SQL injection and XSS to the course `web-security-foundations`. Mention them in a few words where useful; do not teach them again. Use the terms the overview lists as already explained without re-explaining them.

**Rules.**

- Name existing sections by their **uuid** from the overview (for example, insert after `the-seven-layers`, uuid `1c6a50ea-28ea-5d3e-8916-a44731e938fe`, and before `check-your-understanding`, uuid `f8127264-682c-51f7-8376-8a37ce1aac13`). Give a **key** only to new sections, as the slug of their heading.
- Prefer the `comparison` block for the two references and `prose` for the method; keep every table cell under about 15 words.
- Cite real sources for every fact (RFCs, ITU-T X.200, OWASP cheat sheets). Never invent a citation or URL; if you are unsure of one, mark it `"kind": "other"` with `"note": "needs verification"`.
- Add one "In other words" recap after the attacks table, and at most one fun fact (an analogy needs no source).
- Send back the lesson's fingerprint from the overview as `base.lesson_fingerprint`.
- Answer with the JSON package only.
