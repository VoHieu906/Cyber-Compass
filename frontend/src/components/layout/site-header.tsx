import { getTranslations } from "next-intl/server";
import { LocaleSwitcher } from "@/components/shared/locale-switcher";
import { ThemeSwitcher } from "@/components/shared/theme-switcher";
import { AccountNav } from "@/features/auth/components/account-nav";
import { Wordmark } from "./wordmark";

/**
 * The header on every page. Primary navigation (careers, courses, roadmap, assessment,
 * search) is added as each route ships, so the header never links to a page that does
 * not exist yet. See docs/IMPLEMENTATION_ROADMAP.md.
 */
export async function SiteHeader() {
  const t = await getTranslations("common");
  return (
    <header className="sticky top-0 z-[var(--z-sticky-nav)] border-b border-rule bg-[color-mix(in_oklab,var(--color-paper)_92%,transparent)] backdrop-blur-[6px]">
      <div className="container-page flex h-[var(--header-height)] items-center justify-between gap-3">
        <Wordmark label={t("brand")} />
        <div className="flex items-center gap-2 sm:gap-3">
          <div className="flex items-center gap-2 max-sm:hidden">
            <LocaleSwitcher />
            <ThemeSwitcher />
          </div>
          <AccountNav />
        </div>
      </div>
    </header>
  );
}
