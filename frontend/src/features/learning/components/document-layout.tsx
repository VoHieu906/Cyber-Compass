"use client";

import { useTranslations } from "next-intl";
import { useEffect, useRef, type ReactNode } from "react";
import { goTo, useCompassPageItems } from "@/features/compass/page-items";
import { useActiveSection } from "@/lib/use-active-section";
import type { LessonSection } from "../types";
import { Section } from "./sections";

/**
 * The reading layout of a block document (a lesson or a blog post, D55): the sticky "On this page"
 * list with the section being read, the compass's page items, and the sections. Each document
 * type supplies its own header and footer. `enhanced` turns on the long-read additions (heading
 * links, heavy blocks mounted near the viewport), used by posts only for now.
 */
export function DocumentLayout({
  sections,
  header,
  footer,
  enhanced = false,
}: {
  sections: LessonSection[];
  header: ReactNode;
  footer?: ReactNode;
  enhanced?: boolean;
}) {
  const t = useTranslations("learn");
  const headed = sections.filter((s) => s.heading && s.type !== "callout");
  const reading = useActiveSection(headed.map((s) => s.id));
  const list = useRef<HTMLOListElement>(null);
  // A long post's list scrolls on its own: keep the section being read in view inside it.
  useEffect(() => {
    const box = list.current;
    const item = box?.querySelector<HTMLElement>('[aria-current="location"]');
    if (!enhanced || !box || !item) return;
    if (item.offsetTop < box.scrollTop || item.offsetTop + item.offsetHeight > box.scrollTop + box.clientHeight) {
      box.scrollTop = item.offsetTop - box.clientHeight / 2;
    }
  }, [enhanced, reading]);
  useCompassPageItems(
    headed.map((s) => ({
      id: s.id,
      label: s.heading!,
      current: s.id === reading,
      run: () => goTo(document.getElementById(s.id)),
    })),
  );

  return (
    <div className="container-page grid gap-10 py-[var(--space-xl)] lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] lg:gap-[var(--gap-columns)]">
      <aside className="max-lg:hidden">
        <nav
          aria-label={t("onThisPage")}
          className="sticky top-[calc(var(--header-height)+1.5rem)] flex flex-col gap-3"
        >
          <p className="text-sm font-medium text-ink">{t("onThisPage")}</p>
          <ol
            ref={list}
            className={
              enhanced
                ? // A long post's list scrolls on its own rather than running past the screen.
                  "relative flex max-h-[calc(100dvh-var(--header-height)-6rem)] flex-col gap-1 overflow-y-auto overscroll-contain border-l border-rule"
                : "flex flex-col gap-1 border-l border-rule"
            }
          >
            {headed.map((s) => (
              <li key={s.id}>
                <a
                  href={`#${s.id}`}
                  aria-current={s.id === reading ? "location" : undefined}
                  className="-ml-px block border-l border-transparent py-1 pl-3 text-sm text-muted transition-[color,border-color] duration-[var(--dur-micro)] hover:border-rule-2 hover:text-ink aria-[current=location]:border-accent aria-[current=location]:text-ink"
                >
                  {s.heading}
                </a>
              </li>
            ))}
          </ol>
        </nav>
      </aside>

      <article className="flex max-w-[44rem] min-w-0 flex-col gap-10 text-[1.0625rem] leading-[1.7] text-ink-2">
        {header}
        {sections.map((section) => (
          <Section key={section.id} section={section} enhanced={enhanced} />
        ))}
        {footer}
      </article>
    </div>
  );
}
