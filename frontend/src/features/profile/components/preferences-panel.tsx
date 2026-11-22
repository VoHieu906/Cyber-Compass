"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { LocaleSwitcher } from "@/components/shared/locale-switcher";
import { ThemeSwitcher } from "@/components/shared/theme-switcher";
import { Alert } from "@/components/ui/alert";
import { TimezoneField } from "@/features/progress/components/timezone";
import { useNavPrefs } from "@/features/shortcuts/prefs";
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
      <SettingsSection title={t("time")} description={t("timeHint")}>
        <div>
          <TimezoneField onSaved={setSaved} />
        </div>
      </SettingsSection>
      <SettingsSection title={t("compass")} description={t("compassHint")}>
        <CompassSwitch onSaved={setSaved} />
      </SettingsSection>
      {saved === true ? <Alert tone="success">{t("saved")}</Alert> : null}
      {saved === false ? <Alert tone="error">{t("saveFailed")}</Alert> : null}
    </div>
  );
}

/** The compass menu on or off (D51, D53); off unmounts the trigger and its shortcut. */
function CompassSwitch({ onSaved }: { onSaved: (ok: boolean) => void }) {
  const t = useTranslations("settings.preferences");
  const { compassEnabled, save, saving } = useNavPrefs();
  // Shows the new position at once; falls back to the saved value if the save fails.
  const [pending, setPending] = useState<boolean | null>(null);
  return (
    <label className="flex items-center gap-3 text-sm text-ink">
      <input
        type="checkbox"
        role="switch"
        checked={pending ?? compassEnabled}
        disabled={saving}
        onChange={(e) => {
          setPending(e.target.checked);
          void save({ compass_enabled: e.target.checked }).then((ok) => {
            setPending(null);
            onSaved(ok);
          });
        }}
        className="size-4 rounded-chip border-field-edge accent-[var(--color-accent)]"
      />
      {t("compassToggle")}
    </label>
  );
}
