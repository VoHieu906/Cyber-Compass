"use client";

import { createContext, useContext, useEffect, useRef, useState, type ReactNode } from "react";

/** One "On this page" entry a page adds to the compass (D51). */
export type CompassPageItem = { id: string; label: string; run: () => void; current?: boolean };

type Registry = { items: CompassPageItem[]; set: (items: CompassPageItem[]) => void };

const PageItemsContext = createContext<Registry>({ items: [], set: () => {} });

/** Holds the current page's items in React state; wraps the page and the compass (PageShell). */
export function CompassPageProvider({ children }: { children: ReactNode }) {
  const [items, set] = useState<CompassPageItem[]>([]);
  return <PageItemsContext.Provider value={{ items, set }}>{children}</PageItemsContext.Provider>;
}

export const useCompassPageRegistry = () => useContext(PageItemsContext).items;

/**
 * Adds the calling page's sections to the compass while it is mounted, and removes them on
 * unmount (a route change). `run` always calls the page's latest handler.
 */
export function useCompassPageItems(items: CompassPageItem[]) {
  const { set } = useContext(PageItemsContext);
  const latest = useRef(items);
  useEffect(() => {
    latest.current = items;
  });
  const key = items.map((i) => `${i.id}\u0000${i.label}\u0000${i.current ? 1 : 0}`).join("\u0001");
  useEffect(() => {
    set(
      latest.current.map((item) => ({
        ...item,
        run: () => latest.current.find((i) => i.id === item.id)?.run(),
      })),
    );
  }, [key, set]);
  useEffect(() => () => set([]), [set]);
}

/** Scrolls to an element and moves focus there, so keyboard users continue from it. */
export function goTo(element: HTMLElement | null) {
  if (!element) return;
  const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  element.scrollIntoView?.({ behavior: reduce ? "auto" : "smooth", block: "start" });
  if (!element.matches("a, button, input, select, textarea, [tabindex]")) element.setAttribute("tabindex", "-1");
  element.focus({ preventScroll: true });
}
