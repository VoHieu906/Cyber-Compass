"use client";

import { useEffect, useRef } from "react";

/**
 * A thin bar under the sticky header showing how far through the article the reader is. It
 * follows the scroll position directly (no animation), so reduced-motion settings have nothing
 * to switch off; the bar is decorative, and the "On this page" list carries the same information.
 */
export function ReadingProgress({ targetId }: { targetId: string }) {
  const bar = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let frame = 0;
    const update = () => {
      frame = 0;
      const article = document.getElementById(targetId);
      if (!article || !bar.current) return;
      const rect = article.getBoundingClientRect();
      const total = rect.height - window.innerHeight;
      const done = total <= 0 ? 1 : Math.min(1, Math.max(0, -rect.top / total));
      bar.current.style.transform = `scaleX(${done})`;
    };
    const schedule = () => {
      if (!frame) frame = requestAnimationFrame(update);
    };
    update();
    window.addEventListener("scroll", schedule, { passive: true });
    window.addEventListener("resize", schedule);
    return () => {
      window.removeEventListener("scroll", schedule);
      window.removeEventListener("resize", schedule);
      cancelAnimationFrame(frame);
    };
  }, [targetId]);

  return (
    <div
      aria-hidden
      className="pointer-events-none fixed inset-x-0 top-[var(--header-height)] z-[var(--z-sticky-nav)] h-[3px] bg-transparent"
    >
      <div ref={bar} className="h-full origin-left scale-x-0 bg-accent" />
    </div>
  );
}
