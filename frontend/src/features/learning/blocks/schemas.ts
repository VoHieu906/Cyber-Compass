import { z } from "zod";
import "@/lib/zod-config";
import { sceneSchema, scriptSchema } from "@/sandbox/scene3d/schema";
import { diagramDataSchemas, type DiagramKey } from "../diagrams/data";

/*
 * zod mirrors of the backend block contracts (backend/app/Modules/Content/Blocks/schemas/*.json
 * plus the cross-reference checks in Types/*Block.php). contract.test.ts holds both sides to the
 * same fixtures. Renderers parse with these before drawing, so a malformed payload shows a
 * notice instead of breaking the lesson, and the editor uses them for its live preview.
 */

const text = (max: number) => z.string().min(1).max(max);
const id = z.string().regex(/^[a-z0-9][a-z0-9-]{0,39}$/);
const tone = z.enum(["accent", "attn", "muted", "plain"]);

type Ctx = z.RefinementCtx;
type Path = (string | number)[];

/** Records ids, reporting duplicates. Returns the set of ids seen. */
function collectIds(items: { id: string }[], path: Path, ctx: Ctx): Set<string> {
  const ids = new Set<string>();
  items.forEach((item, i) => {
    if (ids.has(item.id))
      ctx.addIssue({ code: "custom", path: [...path, i, "id"], message: `Duplicate id "${item.id}".` });
    ids.add(item.id);
  });
  return ids;
}

function mustExist(ref: string, ids: Set<string>, path: Path, ctx: Ctx): boolean {
  if (ids.has(ref)) return true;
  ctx.addIssue({ code: "custom", path, message: `Unknown id "${ref}".` });
  return false;
}

/** [text](url) inside prose. Only https links render as links; anything else is refused on save. */
export const PROSE_LINK = /\[([^\]\n]+)\]\(([^)\s]*)\)/g;

export function isHttpsUrl(url: string): boolean {
  if (!url.startsWith("https://")) return false;
  try {
    return new URL(url).hostname !== "";
  } catch {
    return false;
  }
}

export const proseSchema = z.strictObject({ text: text(20000) }).superRefine((p, ctx) => {
  for (const [, , url] of p.text.matchAll(PROSE_LINK)) {
    if (!isHttpsUrl(url))
      ctx.addIssue({ code: "custom", path: ["text"], message: `Links must start with https:// (found "${url}").` });
  }
});

export const calloutSchema = z.strictObject({ text: text(2000), tone: z.enum(["info", "tip", "warning"]) });
export const stepsSchema = z.strictObject({ items: z.array(text(600)).min(1).max(30) });
export const codeSchema = z.strictObject({ code: text(10000), language: z.string().regex(/^[a-z0-9+#-]{1,20}$/) });
export const mediaSchema = z.strictObject({
  media: z.string().regex(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/),
  caption: z.string().max(300).nullish(),
});
export const diagramSchema = z
  .strictObject({
    key: z.string().regex(/^[a-z0-9-]{1,40}$/),
    caption: z.string().max(300).nullish(),
    data: z.record(z.string(), z.unknown()),
  })
  .superRefine((p, ctx) => {
    const data = diagramDataSchemas[p.key as DiagramKey];
    if (!data) {
      ctx.addIssue({
        code: "custom",
        path: ["key"],
        message: `Unknown diagram. Allowed: ${Object.keys(diagramDataSchemas).join(", ")}`,
      });
      return;
    }
    for (const issue of data.safeParse(p.data).error?.issues ?? []) {
      ctx.addIssue({ code: "custom", path: ["data", ...issue.path.map(String)], message: issue.message });
    }
  });

export const layerStackSchema = z.strictObject({
  tags_label: text(40).optional(),
  layers: z
    .array(
      z.strictObject({
        tag: text(8).optional(),
        name: text(60),
        meta: text(40).optional(),
        description: text(600),
        facts: z
          .array(z.strictObject({ label: text(40), value: text(160) }))
          .max(6)
          .optional(),
        tags: z.array(text(40)).max(12).optional(),
        note: z
          .strictObject({ tone: z.enum(["info", "tip", "warning"]), label: text(60).optional(), text: text(400) })
          .optional(),
      }),
    )
    .min(2)
    .max(12),
});

export const sequenceSchema = z
  .strictObject({
    actors: z
      .array(z.strictObject({ id, name: text(40) }))
      .min(2)
      .max(5),
    messages: z
      .array(
        z.strictObject({
          from: id,
          to: id,
          label: text(40),
          detail: text(80).optional(),
          explanation: text(600),
          states: z
            .record(id, text(40))
            .refine((s) => Object.keys(s).length <= 5, "Use at most 5 items.")
            .optional(),
        }),
      )
      .min(1)
      .max(20),
  })
  .superRefine((p, ctx) => {
    const actors = collectIds(p.actors, ["actors"], ctx);
    p.messages.forEach((m, i) => {
      const from = mustExist(m.from, actors, ["messages", i, "from"], ctx);
      mustExist(m.to, actors, ["messages", i, "to"], ctx);
      if (from && m.from === m.to) {
        ctx.addIssue({ code: "custom", path: ["messages", i, "to"], message: "A message needs two different actors." });
      }
      for (const actor of Object.keys(m.states ?? {})) mustExist(actor, actors, ["messages", i, "states", actor], ctx);
    });
  });

export const flowSchema = z
  .strictObject({
    direction: z.enum(["down", "right"]).optional(),
    nodes: z
      .array(
        z.strictObject({
          id,
          label: text(60),
          kind: z.enum(["start", "step", "decision", "end"]).optional(),
          detail: text(400).optional(),
        }),
      )
      .min(2)
      .max(30),
    edges: z
      .array(z.strictObject({ from: id, to: id, label: text(30).optional() }))
      .min(1)
      .max(60),
  })
  .superRefine((p, ctx) => {
    const nodes = collectIds(p.nodes, ["nodes"], ctx);
    const seen = new Set<string>();
    p.edges.forEach((e, i) => {
      const from = mustExist(e.from, nodes, ["edges", i, "from"], ctx);
      const to = mustExist(e.to, nodes, ["edges", i, "to"], ctx);
      if (!from || !to) return;
      const key = `${e.from}>${e.to}`;
      if (e.from === e.to) {
        ctx.addIssue({ code: "custom", path: ["edges", i, "to"], message: "An edge needs two different nodes." });
      } else if (seen.has(key)) {
        ctx.addIssue({ code: "custom", path: ["edges", i], message: "This edge is listed twice." });
      }
      seen.add(key);
    });
  });

export const treeSchema = z
  .strictObject({
    nodes: z
      .array(
        z.strictObject({
          id,
          label: text(60),
          parent: id.optional(),
          note: text(40).optional(),
          detail: text(400).optional(),
        }),
      )
      .min(2)
      .max(40),
  })
  .superRefine((p, ctx) => {
    const ids = collectIds(p.nodes, ["nodes"], ctx);
    const parents = new Map(p.nodes.map((n) => [n.id, n.parent ?? null]));
    const roots = p.nodes.filter((n) => n.parent === undefined).length;
    if (roots !== 1) {
      ctx.addIssue({
        code: "custom",
        path: ["nodes"],
        message: `A tree needs exactly one node without a parent (it has ${roots}).`,
      });
    }
    p.nodes.forEach((n, i) => {
      if (n.parent === undefined || !mustExist(n.parent, ids, ["nodes", i, "parent"], ctx)) return;
      const seen = new Set([n.id]);
      for (let at: string | null = n.parent; at !== null; at = parents.get(at) ?? null) {
        if (seen.has(at)) {
          ctx.addIssue({ code: "custom", path: ["nodes", i, "parent"], message: "Parents form a loop." });
          break;
        }
        seen.add(at);
      }
    });
  });

export const DEVICE_KINDS = [
  "router",
  "switch",
  "firewall",
  "server",
  "database",
  "workstation",
  "laptop",
  "phone",
  "access-point",
  "cloud",
  "internet",
  "attacker",
] as const;
const pct = z.number().min(0).max(100);

export const topologySchema = z
  .strictObject({
    aspect: z.enum(["16:9", "4:3", "2:1"]).optional(),
    zones: z
      .array(
        z.strictObject({
          id,
          label: text(40),
          tone: z.enum(["trusted", "untrusted", "dmz", "neutral"]).optional(),
          x: pct,
          y: pct,
          w: z.number().min(5).max(100),
          h: z.number().min(5).max(100),
        }),
      )
      .max(6)
      .optional(),
    devices: z
      .array(
        z.strictObject({
          id,
          label: text(40),
          kind: z.enum(DEVICE_KINDS),
          x: pct,
          y: pct,
          address: text(40).optional(),
          detail: text(400).optional(),
        }),
      )
      .min(2)
      .max(30),
    links: z
      .array(
        z.strictObject({
          from: id,
          to: id,
          label: text(30).optional(),
          style: z.enum(["wired", "wireless", "vpn"]).optional(),
        }),
      )
      .max(60),
  })
  .superRefine((p, ctx) => {
    collectIds(p.zones ?? [], ["zones"], ctx);
    const devices = collectIds(p.devices, ["devices"], ctx);
    (p.zones ?? []).forEach((zone, i) => {
      if (devices.has(zone.id))
        ctx.addIssue({ code: "custom", path: ["zones", i, "id"], message: `Duplicate id "${zone.id}".` });
      if (zone.x + zone.w > 100 || zone.y + zone.h > 100) {
        ctx.addIssue({ code: "custom", path: ["zones", i], message: "The zone goes past the edge of the canvas." });
      }
    });
    p.links.forEach((l, i) => {
      const from = mustExist(l.from, devices, ["links", i, "from"], ctx);
      const to = mustExist(l.to, devices, ["links", i, "to"], ctx);
      if (from && to && l.from === l.to) {
        ctx.addIssue({ code: "custom", path: ["links", i, "to"], message: "A link needs two different devices." });
      }
    });
  });

export const comparisonSchema = z
  .strictObject({
    columns: z.array(text(40)).min(2).max(4),
    rows: z
      .array(z.strictObject({ label: text(60), values: z.array(text(200)).min(2).max(4) }))
      .min(1)
      .max(15),
  })
  .superRefine((p, ctx) => {
    p.rows.forEach((row, i) => {
      if (row.values.length !== p.columns.length) {
        ctx.addIssue({
          code: "custom",
          path: ["rows", i, "values"],
          message: `Give one value per column (${p.columns.length}).`,
        });
      }
    });
  });

export const timelineSchema = z.strictObject({
  events: z
    .array(z.strictObject({ when: text(40), title: text(80), detail: text(400).optional() }))
    .min(2)
    .max(20),
});

export const MAX_ROW_BITS = 64;
export const bitfieldSchema = z
  .strictObject({
    show_decimal: z.boolean().optional(),
    rows: z
      .array(
        z.strictObject({
          label: text(40),
          fields: z
            .array(z.strictObject({ label: text(30), bits: z.string().regex(/^[01]{1,32}$/), tone: tone.optional() }))
            .min(1)
            .max(8),
        }),
      )
      .min(1)
      .max(4),
  })
  .superRefine((p, ctx) => {
    p.rows.forEach((row, i) => {
      const bits = row.fields.reduce((sum, f) => sum + f.bits.length, 0);
      if (bits > MAX_ROW_BITS) {
        ctx.addIssue({
          code: "custom",
          path: ["rows", i, "fields"],
          message: `A row holds at most ${MAX_ROW_BITS} bits (this one has ${bits}).`,
        });
      }
    });
  });

export const CALCULATOR_TOOLS = ["cidr", "chmod", "password-entropy"] as const;
export const CHMOD_PATTERN = /^([0-7]{3,4}|[r-][w-][xsS-][r-][w-][xsS-][r-][w-][xtT-])$/;
export const CIDR_PATTERN = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\/(\d{1,2})$/;

export function isCidr(value: string): boolean {
  const m = CIDR_PATTERN.exec(value);
  return m !== null && m.slice(1, 5).every((o) => Number(o) <= 255) && Number(m[5]) <= 32;
}

export const calculatorSchema = z
  .strictObject({ tool: z.enum(CALCULATOR_TOOLS), initial: text(64).optional() })
  .superRefine((p, ctx) => {
    if (p.initial === undefined) return;
    const value = p.initial.trim();
    if (p.tool === "cidr" && !isCidr(value)) {
      ctx.addIssue({
        code: "custom",
        path: ["initial"],
        message: "Use an address with a prefix, like 192.168.1.10/24.",
      });
    }
    if (p.tool === "chmod" && !CHMOD_PATTERN.test(value)) {
      ctx.addIssue({
        code: "custom",
        path: ["initial"],
        message: "Use octal digits like 754, or letters like rwxr-xr--.",
      });
    }
  });

export const stepThroughSchema = z.strictObject({
  steps: z
    .array(
      z.strictObject({
        title: text(80),
        text: text(800),
        parts: z
          .array(z.strictObject({ label: text(40), tone: tone.optional() }))
          .max(8)
          .optional(),
        state: z
          .array(z.strictObject({ label: text(40), value: text(80) }))
          .max(8)
          .optional(),
      }),
    )
    .min(2)
    .max(20),
});

export const knowledgeCheckSchema = z.strictObject({
  questions: z
    .array(
      z
        .strictObject({
          prompt: text(300),
          options: z
            .array(z.strictObject({ text: text(200), correct: z.boolean() }))
            .min(2)
            .max(6),
          explanation: text(600),
        })
        .refine((q) => q.options.some((o) => o.correct), {
          path: ["options"],
          message: "Mark at least one option as correct.",
        }),
    )
    .min(1)
    .max(3),
});

/** Every block type's payload contract, by section type. */
export const blockSchemas = {
  prose: proseSchema,
  callout: calloutSchema,
  steps: stepsSchema,
  code: codeSchema,
  media: mediaSchema,
  diagram: diagramSchema,
  "layer-stack": layerStackSchema,
  sequence: sequenceSchema,
  flow: flowSchema,
  tree: treeSchema,
  topology: topologySchema,
  comparison: comparisonSchema,
  timeline: timelineSchema,
  bitfield: bitfieldSchema,
  calculator: calculatorSchema,
  "step-through": stepThroughSchema,
  "knowledge-check": knowledgeCheckSchema,
  "scene-3d": sceneSchema,
  "script-3d": scriptSchema,
} as const;

export type LayerStack = z.infer<typeof layerStackSchema>;
export type Sequence = z.infer<typeof sequenceSchema>;
export type Flow = z.infer<typeof flowSchema>;
export type Tree = z.infer<typeof treeSchema>;
export type Topology = z.infer<typeof topologySchema>;
export type Comparison = z.infer<typeof comparisonSchema>;
export type Timeline = z.infer<typeof timelineSchema>;
export type Bitfield = z.infer<typeof bitfieldSchema>;
export type Calculator = z.infer<typeof calculatorSchema>;
export type StepThrough = z.infer<typeof stepThroughSchema>;
export type KnowledgeCheck = z.infer<typeof knowledgeCheckSchema>;
export type Tone = z.infer<typeof tone>;
