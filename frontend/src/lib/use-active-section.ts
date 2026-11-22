"use client";

import { useEffect, useState } from "react";

/**
 * The id of the section being read: the last of `ids` whose top has passed a line just below the
 * sticky header. One IntersectionObserver; used by the lesson's "On this page" and the compass.
 */
export function useActiveSection(ids: string[]): string | null {
  const [active, setActive] = useState<string | null>(null);
  const key = ids.join(" ");
  useEffect(() => {
    if (typeof IntersectionObserver === "undefined" || !key) return;
    const elements = key.split(" ").map((id) => document.getElementById(id)).filter((el): el is HTMLElement => el !== null);
    const update = () => {
      const line = window.innerHeight * 0.3;
      let current: string | null = null;
      for (const el of elements) if (el.getBoundingClientRect().top <= line) current = el.id;
      setActive(current ?? elements[0]?.id ?? null);
    };
    // Thresholds at the line make the observer fire as a section crosses it.
    const observer = new IntersectionObserver(update, { rootMargin: "0px 0px -70% 0px", threshold: [0, 1] });
    elements.forEach((el) => observer.observe(el));
    update();
    return () => observer.disconnect();
  }, [key]);
  return active;
}
