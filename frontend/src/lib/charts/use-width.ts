"use client";

import { useEffect, useRef, useState } from "react";

/**
 * The rendered width of an element, kept current with a ResizeObserver. Charts draw at their real
 * pixel width so text and 4px corners never stretch (no viewBox scaling). 0 until measured.
 */
export function useWidth<T extends HTMLElement>() {
  const ref = useRef<T>(null);
  const [width, setWidth] = useState(0);

  useEffect(() => {
    const element = ref.current;
    if (!element) return;
    setWidth(element.getBoundingClientRect().width);
    if (typeof ResizeObserver === "undefined") return;
    const observer = new ResizeObserver(([entry]) => setWidth(entry.contentRect.width));
    observer.observe(element);
    return () => observer.disconnect();
  }, []);

  return [ref, width] as const;
}
