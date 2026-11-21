"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

const TABS = [
  { href: "/roadmap", key: "roadmap" },
  { href: "/roadmap/map", key: "map" },
] as const;

/** Switches between my roadmap and the knowledge map: two pages, so each has its own URL. */
export function RoadmapTabs() {
  const t = useTranslations("roadmap.tabs");
  const pathname = usePathname();
  return (
    <nav aria-label={t("label")}>
      <ul className="inline-flex rounded-control border border-rule bg-paper-2 p-0.5">
        {TABS.map((tab) => {
          const current = pathname === tab.href;
          return (
            <li key={tab.href}>
              <Link
                href={tab.href}
                aria-current={current ? "page" : undefined}
                className={cn(
                  "block rounded-chip px-3 py-1.5 text-sm font-medium",
                  "transition-[background-color,color] duration-[var(--dur-micro)] ease-out",
                  current ? "bg-accent-soft text-ink" : "text-muted hover:text-ink",
                )}
              >
                {t(tab.key)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
