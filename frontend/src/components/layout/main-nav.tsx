"use client";

import { Menu, X } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { LocaleSwitcher } from "@/components/shared/locale-switcher";
import { ThemeSwitcher } from "@/components/shared/theme-switcher";
import { cn } from "@/lib/utils";

/** Routes that exist today. Roadmap, assessment and search join as their phases ship. */
const LINKS = [
  { href: "/courses", key: "courses" },
  { href: "/careers", key: "careers" },
] as const;

function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}

export function MainNav() {
  const t = useTranslations("nav");
  const pathname = usePathname();
  return (
    <nav aria-label={t("primary")} className="max-md:hidden">
      <ul className="flex items-center gap-1">
        {LINKS.map((link) => {
          const active = isActive(pathname, link.href);
          return (
            <li key={link.href}>
              <Link
                href={link.href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "rounded-control px-3 py-2 text-sm font-medium transition-[color,background-color] duration-[var(--dur-micro)]",
                  active ? "text-ink" : "text-muted hover:bg-paper-3 hover:text-ink",
                )}
              >
                <span className={cn(active && "underline decoration-accent decoration-2 underline-offset-[10px]")}>{t(link.key)}</span>
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}

/** Below the md breakpoint: one button that opens the links and the display preferences. */
export function MobileMenu() {
  const t = useTranslations("nav");
  const pathname = usePathname();
  const [open, setOpen] = useState(false);
  const panelId = useId();
  const buttonRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [open]);

  return (
    <div className="md:hidden">
      <button
        ref={buttonRef}
        type="button"
        aria-expanded={open}
        aria-controls={panelId}
        aria-label={open ? t("closeMenu") : t("menu")}
        onClick={() => setOpen((v) => !v)}
        className="flex size-9 items-center justify-center rounded-control border border-rule text-ink"
      >
        {open ? <X aria-hidden className="size-4" /> : <Menu aria-hidden className="size-4" />}
      </button>
      <div
        id={panelId}
        hidden={!open}
        className="absolute inset-x-0 top-[var(--header-height)] border-b border-rule bg-paper shadow-[var(--shadow-overlay)]"
      >
        <div className="container-page flex flex-col gap-5 py-5">
          <nav aria-label={t("primary")}>
            <ul className="flex flex-col">
              {LINKS.map((link) => (
                <li key={link.href}>
                  <Link
                    href={link.href}
                    onClick={() => setOpen(false)}
                    aria-current={isActive(pathname, link.href) ? "page" : undefined}
                    className="block border-b border-rule py-3 text-[length:var(--text-lg)] font-medium text-ink aria-[current=page]:text-accent"
                  >
                    {t(link.key)}
                  </Link>
                </li>
              ))}
            </ul>
          </nav>
          <div className="flex flex-wrap gap-2">
            <LocaleSwitcher long />
            <ThemeSwitcher iconOnly={false} />
          </div>
        </div>
      </div>
    </div>
  );
}
