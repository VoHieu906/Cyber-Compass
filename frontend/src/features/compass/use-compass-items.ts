"use client";

import {
  ArrowDownToLine, ArrowUpToLine, Bell, Briefcase, ClipboardCheck, FlaskConical, GraduationCap, LayoutDashboard, ListTree, Route, Search, Settings, ShieldCheck, type LucideIcon,
} from "lucide-react";
import { useTranslations } from "next-intl";
import { usePathname } from "next/navigation";
import { ADMIN_AREAS } from "@/features/admin/components/admin-nav";
import { useActiveAssessment } from "@/features/assessment/hooks";
import { useCurrentUser } from "@/features/auth/hooks";
import { useUnreadCount } from "@/features/notifications/hooks";
import { SETTINGS_TABS } from "@/features/profile/components/settings-nav";
import { openSearch } from "@/features/search/components/search-dialog";
import { isActive, visibleLinks, type NavLink } from "@/lib/navigation";
import { useCompassPageRegistry } from "./page-items";

export type CompassItem = {
  id: string;
  label: string;
  Icon: LucideIcon;
  href?: string;
  run?: () => void;
  children?: CompassItem[];
  /** A count shown on the segment (unread notifications). */
  badge?: number;
  /** The page (or page section) the learner is on: `aria-current`. */
  current?: boolean;
};

const LINK_ICONS: Record<NavLink["key"], LucideIcon> = {
  dashboard: LayoutDashboard,
  courses: GraduationCap,
  careers: Briefcase,
  assessment: ClipboardCheck,
  roadmap: Route,
  labs: FlaskConical,
};

const scrollPage = (top: number) => {
  const reduce = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  window.scrollTo({ top, behavior: reduce ? "auto" : "smooth" });
};

/**
 * The compass tree (D51), built only from the shared route lists (`lib/navigation.ts`,
 * `SETTINGS_TABS`, `ADMIN_AREAS`) and the page's registered sections. Visibility follows the
 * header: the dashboard, notifications and settings once signed in, the admin console for admins.
 */
export function useCompassItems(): CompassItem[] {
  const t = useTranslations();
  const pathname = usePathname();
  const { data: user } = useCurrentUser();
  const signedIn = Boolean(user);
  const unread = useUnreadCount(signedIn).data?.count ?? 0;
  const assessment = useActiveAssessment(signedIn).data;
  const pageItems = useCompassPageRegistry();

  const link = (href: string, label: string, Icon: LucideIcon, exact = false): CompassItem => ({
    id: href,
    href,
    label,
    Icon,
    current: exact ? pathname === href : isActive(pathname, href),
  });

  const childrenOf = (key: NavLink["key"]): CompassItem[] | undefined => {
    if (key === "roadmap") {
      return [link("/roadmap", t("compass.roadmapOutline"), Route, true), link("/roadmap/map", t("compass.roadmapMap"), ListTree)];
    }
    if (key === "assessment" && signedIn) {
      const result = assessment?.latest_result;
      return [
        link("/assessment", t(assessment?.open_attempt ? "compass.assessmentContinue" : "compass.assessmentStart"), ClipboardCheck, true),
        ...(result ? [link(`/assessment/attempts/${result.id}/result`, t("compass.assessmentResult"), ClipboardCheck)] : []),
      ];
    }
    return undefined;
  };

  const items: CompassItem[] = visibleLinks(signedIn).map((l) => {
    const children = childrenOf(l.key);
    return { ...link(l.href, t(`nav.${l.key}`), LINK_ICONS[l.key]), children, current: isActive(pathname, l.href) };
  });

  if (user) {
    items.push({ ...link("/notifications", t("compass.notifications"), Bell), badge: unread || undefined });
    items.push({
      id: "settings",
      label: t("nav.settings"),
      Icon: Settings,
      href: SETTINGS_TABS[0].href,
      current: isActive(pathname, "/settings"),
      children: SETTINGS_TABS.map((tab) => link(tab.href, t(`settings.tabs.${tab.key}`), tab.Icon, true)),
    });
    if (user.role === "admin") {
      items.push({
        id: "admin",
        label: t("nav.admin"),
        Icon: ShieldCheck,
        href: "/admin",
        current: isActive(pathname, "/admin"),
        children: ADMIN_AREAS.map((area) => link(area.href, t(`admin.nav.${area.key}`), area.Icon, area.exact)),
      });
    }
  }

  items.push({ id: "search", label: t("compass.search"), Icon: Search, run: openSearch });

  if (pageItems.length > 0) {
    items.push({
      id: "page",
      label: t("compass.onThisPage"),
      Icon: ListTree,
      children: pageItems.map((p) => ({ id: `page:${p.id}`, label: p.label, Icon: ListTree, run: p.run, current: p.current })),
    });
  }

  items.push({ id: "top", label: t("compass.top"), Icon: ArrowUpToLine, run: () => scrollPage(0) });
  items.push({ id: "bottom", label: t("compass.bottom"), Icon: ArrowDownToLine, run: () => scrollPage(document.documentElement.scrollHeight) });
  return items;
}
