"use client";

import { Bell, Keyboard, Lock, SlidersHorizontal, Target, UserRound } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

/** Settings tabs; the compass lists them too (D51), with the icons. */
export const SETTINGS_TABS = [
  { href: "/settings/profile", key: "profile", Icon: UserRound },
  { href: "/settings/goals", key: "goals", Icon: Target },
  { href: "/settings/security", key: "security", Icon: Lock },
  { href: "/settings/preferences", key: "preferences", Icon: SlidersHorizontal },
  { href: "/settings/notifications", key: "notifications", Icon: Bell },
  { href: "/settings/shortcuts", key: "shortcuts", Icon: Keyboard },
] as const;

/**
 * Settings sections as real routes, so each tab has its own URL and back button.
 * A rail on wide screens, a scrollable strip on phones.
 */
export function SettingsNav() {
  const t = useTranslations("settings.tabs");
  const tSettings = useTranslations("settings");
  const pathname = usePathname();
  return (
    <nav aria-label={tSettings("title")} className="-mx-[var(--gutter)] overflow-x-auto px-[var(--gutter)] lg:sticky-panel lg:-m-1 lg:self-start lg:p-1">
      <ul className="flex gap-1 border-b border-rule lg:flex-col lg:border-b-0">
        {SETTINGS_TABS.map((tab) => {
          const active = pathname === tab.href;
          return (
            <li key={tab.href} className="shrink-0">
              <Link
                href={tab.href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex items-center whitespace-nowrap px-3 py-2.5 text-sm font-medium transition-[color,background-color,border-color] duration-[var(--dur-micro)]",
                  "-mb-px border-b-2 lg:mb-0 lg:rounded-control lg:border-b-0 lg:border-l-2 lg:rounded-l-none",
                  active
                    ? "border-accent text-ink lg:bg-paper-2"
                    : "border-transparent text-muted hover:text-ink lg:hover:bg-paper-2",
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
