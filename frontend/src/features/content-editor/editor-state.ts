import type { BlockTypeInfo, EditorSection } from "./types";

/** Server validation errors keyed like the request body (`sections.3.payload.items.0`). */
export type FieldErrors = Record<string, string[]>;

/** The first message for an exact key. */
export function errorAt(errors: FieldErrors, key: string): string | undefined {
  return errors[key]?.[0];
}

/**
 * Messages under a prefix, keyed by the rest of the path ("" for the prefix itself).
 * errorsUnder(e, "sections.2.payload") -> { "text": [...], "data.layers.0.name": [...] }
 */
export function errorsUnder(errors: FieldErrors, prefix: string): FieldErrors {
  const out: FieldErrors = {};
  for (const [key, messages] of Object.entries(errors)) {
    if (key === prefix) out[""] = messages;
    else if (key.startsWith(`${prefix}.`)) out[key.slice(prefix.length + 1)] = messages;
  }
  return out;
}

export function countErrors(errors: FieldErrors): number {
  return Object.keys(errors).length;
}

/** Moves one item; out-of-range moves return the list unchanged. */
export function moveItem<T>(items: readonly T[], from: number, to: number): T[] {
  if (from === to || from < 0 || to < 0 || from >= items.length || to >= items.length) return [...items];
  const next = [...items];
  const [item] = next.splice(from, 1);
  next.splice(to, 0, item);
  return next;
}

/**
 * A new section of a type, pre-filled with the type's own example from the API, so the
 * author starts from something valid and edits it. An image starts empty: the example's
 * uuid points at no real upload.
 */
export function newSection(type: BlockTypeInfo): EditorSection {
  if (type.key === "media") return { type: "media", heading: null, payload: { media: "", caption: null } };
  return { type: type.key, heading: null, payload: structuredClone(type.example) };
}

/** Stable comparison for "are there unsaved changes". Key order is irrelevant; undefined equals absent. */
export function sameCopy<T>(a: T, b: T): boolean {
  return stableStringify(a) === stableStringify(b);
}

function stableStringify(value: unknown): string {
  return JSON.stringify(value, (_key, v: unknown) =>
    v && typeof v === "object" && !Array.isArray(v)
      ? Object.fromEntries(
          Object.entries(v as Record<string, unknown>)
            .filter(([, x]) => x !== undefined)
            .sort(([x], [y]) => x.localeCompare(y)),
        )
      : v,
  );
}

/** JSON text for the JSON editor, and back. Parse errors are returned, never thrown. */
export function toJsonText(value: unknown): string {
  return JSON.stringify(value ?? {}, null, 2);
}

export function parseJsonObject(
  text: string,
): { ok: true; value: Record<string, unknown> } | { ok: false; error: string } {
  try {
    const value: unknown = JSON.parse(text);
    if (!value || typeof value !== "object" || Array.isArray(value)) return { ok: false, error: "object" };
    return { ok: true, value: value as Record<string, unknown> };
  } catch (e) {
    return { ok: false, error: e instanceof Error ? e.message : "syntax" };
  }
}
