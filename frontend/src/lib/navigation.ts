/**
 * The primary navigation, read by the header (`MainNav`, `MobileMenu`) and the compass (D51).
 * Only routes that exist; search is the header's search dialog (D36). The dashboard shows once
 * signed in; signed-out visitors who open the assessment, roadmap or labs are sent to log in first.
 */
export const LINKS = [
  { href: "/dashboard", key: "dashboard", signedIn: true },
  { href: "/courses", key: "courses" },
  { href: "/careers", key: "careers" },
  { href: "/assessment", key: "assessment" },
  { href: "/roadmap", key: "roadmap" },
  { href: "/labs", key: "labs" },
] as const;

export type NavLink = (typeof LINKS)[number];

/** The links a visitor sees: the dashboard only once signed in, so the guest header stays short. */
export const visibleLinks = (signedIn: boolean): NavLink[] => LINKS.filter((link) => !("signedIn" in link) || signedIn);

export function isActive(pathname: string, href: string) {
  return pathname === href || pathname.startsWith(`${href}/`);
}
