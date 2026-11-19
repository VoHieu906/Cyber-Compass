"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { LocaleSwitcher } from "@/components/shared/locale-switcher";
import { ThemeSwitcher } from "@/components/shared/theme-switcher";
import { Alert } from "@/components/ui/alert";
import { SettingsSection } from "./settings-section";

export function PreferencesPanel() {
  const t = useTranslations("settings.preferences");
  const [saved, setSaved] = useState<boolean | null>(null);

  return (
    <div className="flex flex-col gap-8">
      <SettingsSection title={t("appearance")} description={t("appearanceHint")}>
        <div>
          <ThemeSwitcher iconOnly={false} onSaved={setSaved} />
        </div>
      </SettingsSection>
      <SettingsSection title={t("language")} description={t("languageHint")}>
        <div>
          <LocaleSwitcher long onSaved={setSaved} />
        </div>
      </SettingsSection>
      {saved === true ? <Alert tone="success">{t("saved")}</Alert> : null}
      {saved === false ? <Alert tone="error">{t("saveFailed")}</Alert> : null}
    </div>
  );
}
