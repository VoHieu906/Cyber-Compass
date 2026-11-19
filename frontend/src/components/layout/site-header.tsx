import { getTranslations } from "next-intl/server";
import { LocaleSwitcher } from "@/components/shared/locale-switcher";
import { ThemeSwitcher } from "@/components/shared/theme-switcher";
import { AccountNav } from "@/features/auth/components/account-nav";
import { MainNav, MobileMenu } from "./main-nav";
import { Wordmark } from "./wordmark";

/** The header on every page. Navigation lists only routes that exist (see main-nav.tsx). */
export async function SiteHeader() {
  const t = await getTranslations("common");
  return (
    <header className="sticky top-0 z-[var(--z-sticky-nav)] border-b border-rule bg-[color-mix(in_oklab,var(--color-paper)_92%,transparent)] backdrop-blur-[6px]">
      <div className="container-page relative flex h-[var(--header-height)] items-center justify-between gap-3">
        <div className="flex items-center gap-6">
          <Wordmark label={t("brand")} />
          <MainNav />
        </div>
        <div className="flex items-center gap-2 sm:gap-3">
          <div className="flex items-center gap-2 max-md:hidden">
            <LocaleSwitcher />
            <ThemeSwitcher />
          </div>
          <AccountNav />
          <MobileMenu />
        </div>
      </div>
    </header>
  );
}
