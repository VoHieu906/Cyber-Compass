import { useCallback, useState } from "react";
import type { CourseGraph } from "@/lib/graph/shapes/schemas";
import type { MapSuggestion } from "./types";

/*
 * Accepting course map suggestions (F4 Part C, D63). The server proposes; accepting only changes
 * the panel's working copy, which the admin saves as usual (audited, lock_version checked).
 * Both functions are idempotent and check the working copy, so a suggestion that no longer fits
 * (the pair is related already, the cell was taken meanwhile) is simply not applied.
 */

export type GraphSuggestion = Extract<MapSuggestion, { type: "relation" | "placement" }>;

const samePair = (a: { from: string; to: string }, b: { from: string; to: string }) =>
  (a.from === b.from && a.to === b.to) || (a.from === b.to && a.to === b.from);

/** Whether the working copy already has what the suggestion proposes. */
export function isApplied(graph: CourseGraph, s: GraphSuggestion): boolean {
  if (s.type === "relation") return graph.edges.some((e) => samePair(e, s.edge));
  const p = graph.placement?.[s.lesson];
  return Boolean(p && p.col === s.placement.col && p.row === s.placement.row && (p.lane ?? "") === (s.placement.lane ?? ""));
}

/** The working copy with the suggestions applied, skipping any that would duplicate a pair or a cell. */
export function applySuggestions(graph: CourseGraph, suggestions: GraphSuggestion[]): CourseGraph {
  let next = graph;
  for (const s of suggestions) {
    if (s.type === "relation") {
      if (next.edges.some((e) => samePair(e, s.edge)) || next.edges.length >= 80) continue;
      next = { ...next, edges: [...next.edges, s.edge] };
    } else {
      const placement = next.placement ?? {};
      const taken = Object.entries(placement).some(
        ([slug, p]) => slug !== s.lesson && p.col === s.placement.col && p.row === s.placement.row && (p.lane ?? "") === (s.placement.lane ?? ""),
      );
      if (placement[s.lesson] || taken) continue;
      next = { ...next, placement: { ...placement, [s.lesson]: s.placement } };
    }
  }
  return next;
}

/**
 * Dismissed suggestions, per browser and scope (a course or a lesson): the simplest store that
 * never brings a dismissed suggestion back on the next visit. A suggestion whose content changes
 * gets a new key, so it is offered again.
 */
export function useDismissed(scope: string) {
  const storageKey = `cybercompass.dismissedSuggestions.${scope}`;
  const [dismissed, setDismissed] = useState<Set<string>>(() => {
    if (typeof window === "undefined") return new Set();
    try {
      return new Set(JSON.parse(window.localStorage.getItem(storageKey) ?? "[]") as string[]);
    } catch {
      return new Set();
    }
  });
  const store = useCallback(
    (next: Set<string>) => {
      setDismissed(next);
      try {
        window.localStorage.setItem(storageKey, JSON.stringify([...next]));
      } catch {
        // Storage full or blocked: dismissal lasts until the page is left.
      }
    },
    [storageKey],
  );
  return {
    dismissed,
    dismiss: (key: string) => store(new Set([...dismissed, key])),
    restoreAll: () => store(new Set()),
  };
}

/** A dismissal key that changes with the suggestion's content. */
export function suggestionKey(s: MapSuggestion): string {
  if (s.type === "relation") return `${s.key}:${s.edge.from}>${s.edge.to}:${s.edge.type}`;
  if (s.type === "placement") return `${s.key}:${s.placement.lane ?? ""}:${s.placement.row}:${s.placement.col}`;
  if (s.type === "problem") return `${s.key}:${s.problem}`;
  return s.key;
}
