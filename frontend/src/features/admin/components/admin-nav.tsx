"use client";

import { BookOpenText, FolderTree, Gauge, Newspaper, ScrollText, Users } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

/**
 * Admin areas. Adding an area (questions in Phase 4) is a route
 * folder under app/(admin)/admin plus one entry here.
 */
export const ADMIN_AREAS = [
  { href: "/admin", key: "overview", Icon: Gauge, exact: true },
  { href: "/admin/lessons", key: "lessons", Icon: BookOpenText, exact: false },
  { href: "/admin/courses", key: "courses", Icon: FolderTree, exact: false },
  { href: "/admin/posts", key: "posts", Icon: Newspaper, exact: false },
  { href: "/admin/users", key: "users", Icon: Users, exact: false },
  { href: "/admin/audit", key: "audit", Icon: ScrollText, exact: false },
] as const;

export function AdminNav() {
  const t = useTranslations("admin.nav");
  const pathname = usePathname();
  return (
    <nav
      aria-label={t("label")}
      className="-mx-[var(--gutter)] overflow-x-auto px-[var(--gutter)] lg:sticky-panel lg:-m-1 lg:self-start lg:p-1"
    >
      <ul className="flex gap-1 border-b border-rule lg:flex-col lg:border-b-0">
        {ADMIN_AREAS.map(({ href, key, Icon, exact }) => {
          const active = exact ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
          return (
            <li key={href} className="shrink-0">
              <Link
                href={href}
                aria-current={active ? "page" : undefined}
                className={cn(
                  "flex items-center gap-2.5 px-3 py-2.5 text-sm font-medium whitespace-nowrap transition-[color,background-color,border-color] duration-[var(--dur-micro)] [&_svg]:size-4",
                  "-mb-px border-b-2 lg:mb-0 lg:rounded-control lg:rounded-l-none lg:border-b-0 lg:border-l-2",
                  active
                    ? "border-attn text-ink lg:bg-paper-2 [&_svg]:text-attn"
                    : "border-transparent text-muted hover:text-ink active:bg-paper-3 lg:hover:bg-paper-2",
                )}
              >
                <Icon aria-hidden />
                {t(key)}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
