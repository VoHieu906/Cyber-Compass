import type { CheckpointDef, PathMessages, Scenario } from "./types";

/* Pure helpers of the lab editor: what changed, which messages belong to a part, checkpoint shapes. */

function sortKeys(value: unknown): unknown {
  if (Array.isArray(value)) return value.map(sortKeys);
  if (value && typeof value === "object")
    return Object.fromEntries(
      Object.entries(value as Record<string, unknown>)
        .sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0))
        .map(([k, v]) => [k, sortKeys(v)]),
    );
  return value;
}

/** JSON with sorted keys: MySQL hands objects back in its own key order, which is not a change. */
export const canonical = (value: unknown) => JSON.stringify(sortKeys(value));

export const sameContent = (a: unknown, b: unknown) => canonical(a) === canonical(b);

/** The messages at a path or below it (`scenario.checkpoints` includes `scenario.checkpoints.0.path`). */
export function messagesAt(messages: PathMessages, ...prefixes: string[]): PathMessages {
  return Object.fromEntries(
    Object.entries(messages).filter(([path]) => prefixes.some((p) => path === p || path.startsWith(`${p}.`))),
  );
}

/** The messages of several sources, without repeating one. */
export function mergeMessages(...sources: PathMessages[]): PathMessages {
  const out: PathMessages = {};
  for (const source of sources)
    for (const [path, list] of Object.entries(source)) out[path] = [...new Set([...(out[path] ?? []), ...list])];
  return out;
}

export const countMessages = (messages: PathMessages) => Object.values(messages).reduce((n, list) => n + list.length, 0);

export function parseJson(text: string): { ok: true; value: unknown } | { ok: false; error: string } {
  try {
    return { ok: true, value: JSON.parse(text) };
  } catch (e) {
    return { ok: false, error: e instanceof SyntaxError ? e.message : "Invalid JSON" };
  }
}

export const toJsonText = (value: unknown) => JSON.stringify(value, null, 2);

/** What a new checkpoint of each kind starts with (the kind-specific part). */
export const KIND_TEMPLATES: Record<string, Record<string, unknown>> = {
  answer: { answers: [""] },
  read: { path: "/" },
  traffic: { packets: [{ proto: "tcp", port: 80, source: "203.0.113.1", expect: "allow" }] },
  request: { route: "" },
};

export function newCheckpoint(kind: string, taken: string[]): CheckpointDef {
  let n = taken.length + 1;
  while (taken.includes(`task-${n}`)) n++;
  return { id: `task-${n}`, kind, title: "", ...(KIND_TEMPLATES[kind] ?? {}) };
}

/** A checkpoint's fields apart from the ones every kind has. */
export function kindFields(checkpoint: CheckpointDef): Record<string, unknown> {
  const rest: Record<string, unknown> = { ...checkpoint };
  for (const common of ["id", "title", "hint", "kind"]) delete rest[common];
  return rest;
}

export const withoutCheckpoints = (scenario: Scenario): Record<string, unknown> => {
  const { checkpoints: _checkpoints, ...rest } = scenario;
  void _checkpoints;
  return rest;
};

/** The solutions panel's verdict on one entry, from the last check. */
export function entryStatus(errors: PathMessages, kind: "solutions" | "decoys", index: number): "pass" | "fail" {
  return errors[`solutions.${kind}.${index}`] || errors[`solutions.${kind}.${index}.actions`] ? "fail" : "pass";
}

/** What is sent on save: empty lines in the accepted answers are the box's blank line, not an answer. */
export function cleanDefinition<T extends { scenario: Scenario }>(def: T): T {
  return {
    ...def,
    scenario: {
      ...def.scenario,
      checkpoints: def.scenario.checkpoints.map((c) =>
        Array.isArray(c.answers) ? { ...c, answers: (c.answers as string[]).map((a) => a.trim()).filter((a) => a !== "") } : c,
      ),
    },
  };
}
