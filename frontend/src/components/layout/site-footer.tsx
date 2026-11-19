import { getTranslations } from "next-intl/server";
import { LocaleSwitcher } from "@/components/shared/locale-switcher";
import { ThemeSwitcher } from "@/components/shared/theme-switcher";
import { Wordmark } from "./wordmark";

export async function SiteFooter() {
  const t = await getTranslations();
  return (
    <footer className="mt-auto border-t border-rule">
      <div className="container-page flex flex-col gap-6 py-10 sm:flex-row sm:items-end sm:justify-between">
        <div className="flex flex-col gap-2">
          <Wordmark label={t("common.brand")} />
          <p className="text-sm">{t("footer.tagline")}</p>
        </div>
        {/* On phones the header has no room for these, so they live here. */}
        <div className="flex flex-wrap items-center gap-2 sm:hidden">
          <LocaleSwitcher />
          <ThemeSwitcher />
        </div>
        <p className="text-sm">{t("footer.project")}</p>
      </div>
    </footer>
  );
}
