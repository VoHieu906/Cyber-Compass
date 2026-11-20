import { z } from "zod";
import type { LayerStack, Sequence } from "../blocks/schemas";

/*
 * Data shapes of the bespoke diagrams, by key (backend schemas/diagrams/<key>.json).
 * osi-model and tcp-handshake are legacy: their content moved to the generic layer-stack and
 * sequence blocks (Part C2). Old drafts and revisions may still hold them, so they still
 * validate and render, through the generic components, via the adapters below.
 */

export const ciaSchema = z.object({
  properties: z
    .array(
      z.object({
        key: z.string(),
        name: z.string(),
        definition: z.string(),
        example: z.string(),
        controls: z.array(z.string()),
      }),
    )
    .length(3),
});

export const osiSchema = z.object({
  layers: z
    .array(
      z.object({
        number: z.number().int(),
        name: z.string().min(1),
        unit: z.string().min(1),
        role: z.string().min(1),
        protocols: z.array(z.string()),
        attack: z.string().min(1),
      }),
    )
    .min(1)
    .max(12),
});

export const handshakeSchema = z.object({
  participants: z.object({ client: z.string(), server: z.string() }),
  steps: z
    .array(
      z.object({
        from: z.enum(["client", "server"]),
        label: z.string(),
        fields: z.string(),
        explanation: z.string(),
        states: z.object({ client: z.string(), server: z.string() }),
      }),
    )
    .min(1),
});

export const diagramDataSchemas = {
  "cia-triad": ciaSchema,
  "osi-model": osiSchema,
  "tcp-handshake": handshakeSchema,
} as const;

export type DiagramKey = keyof typeof diagramDataSchemas;

export function osiToLayerStack(data: z.infer<typeof osiSchema>): LayerStack {
  return {
    tags_label: "Example protocols",
    layers: data.layers.map((l) => ({
      tag: String(l.number),
      name: l.name,
      meta: l.unit,
      description: l.role,
      tags: l.protocols,
      note: { tone: "warning", label: "Attack at this layer", text: l.attack },
    })),
  };
}

export function handshakeToSequence(data: z.infer<typeof handshakeSchema>): Sequence {
  const other = { client: "server", server: "client" } as const;
  return {
    actors: [
      { id: "client", name: data.participants.client },
      { id: "server", name: data.participants.server },
    ],
    messages: data.steps.map((s) => ({
      from: s.from,
      to: other[s.from],
      label: s.label,
      ...(s.fields ? { detail: s.fields } : {}),
      explanation: s.explanation,
      states: s.states,
    })),
  };
}
