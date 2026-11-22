"use client";

import { useEffect, useRef, useState, type ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * Renders `children` only once the placeholder comes within a screen or so of the viewport, so a
 * long post does not lay out every graph and calculator on load. The placeholder keeps roughly
 * the block's size, and mounting happens well before it scrolls into view, so the text being
 * read does not move.
 */
export function LazyMount({ children, className }: { children: ReactNode; className: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [near, setNear] = useState(false);

  useEffect(() => {
    if (near) return;
    if (typeof IntersectionObserver === "undefined") {
      const frame = requestAnimationFrame(() => setNear(true));
      return () => cancelAnimationFrame(frame);
    }
    const node = ref.current;
    if (!node) return;
    const observer = new IntersectionObserver(([entry]) => entry.isIntersecting && setNear(true), {
      rootMargin: "1200px 0px",
    });
    observer.observe(node);
    return () => observer.disconnect();
  }, [near]);

  if (near) return <>{children}</>;
  return <div ref={ref} aria-hidden className={cn("rounded-control border border-rule bg-paper-3", className)} />;
}
