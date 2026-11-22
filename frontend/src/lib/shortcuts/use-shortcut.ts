"use client";

import { createContext, useContext, useEffect, useMemo, useRef } from "react";
import { ACTIONS, bindingsFor, matches, typing, type ActionId, type Binding, type Overrides } from "./registry";

/** The learner's overrides (account or this browser); the provider lives in features/shortcuts. */
export const ShortcutOverridesContext = createContext<Overrides>({});

export function useBindings(id: ActionId): Binding[] {
  const overrides = useContext(ShortcutOverridesContext);
  const bindings = bindingsFor(id, overrides);
  const key = JSON.stringify(bindings);
  // Stable while the bindings are the same, so listeners re-register only on a real change.
  // eslint-disable-next-line react-hooks/exhaustive-deps
  return useMemo(() => bindings, [key]);
}

/**
 * Runs `handler` on the action's key press. Plain-key shortcuts stay quiet while the person
 * types in a field; combos marked `inFields` (Ctrl+K, Ctrl+S, the compass) work there too.
 */
export function useShortcut(id: ActionId, handler: (e: KeyboardEvent) => void, enabled = true) {
  const bindings = useBindings(id);
  const handlerRef = useRef(handler);
  useEffect(() => {
    handlerRef.current = handler;
  });
  useEffect(() => {
    if (!enabled || bindings.length === 0) return;
    const inFields = "inFields" in ACTIONS[id] && ACTIONS[id].inFields;
    const onKey = (e: KeyboardEvent) => {
      if (!inFields && typing(e.target)) return;
      if (!bindings.some((b) => matches(e, b))) return;
      e.preventDefault();
      handlerRef.current(e);
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [bindings, enabled, id]);
}
