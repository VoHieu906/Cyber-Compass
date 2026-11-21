"use client";

import { useTranslations } from "next-intl";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { SettingsSection } from "@/features/profile/components/settings-section";
import { useNotificationPreferences, useSavePreference } from "../hooks";
import type { NotificationPreference } from "../types";

/** In the app and email, per notification type (D34). Each switch saves on its own. */
export function NotificationSettings() {
  const t = useTranslations("settings.notifications");
  const prefs = useNotificationPreferences();
  const save = useSavePreference();
  const [saved, setSaved] = useState<boolean | null>(null);

  const change = (pref: NotificationPreference, channel: "in_app" | "email", on: boolean) =>
    save.mutate({ ...pref, [channel]: on }, { onSuccess: () => setSaved(true), onError: () => setSaved(false) });

  return (
    <SettingsSection title={t("title")} description={t("lede")}>
      {prefs.isError ? (
        <Alert tone="error">{t("loadError")}</Alert>
      ) : !prefs.data ? (
        <div role="status" aria-label={t("loading")} className="h-64" />
      ) : (
        <div className="flex flex-col gap-4">
          <table className="w-full border-collapse text-sm">
            <caption className="sr-only">{t("title")}</caption>
            <thead>
              <tr className="border-b border-rule text-left text-muted">
                <th scope="col" className="py-2 pr-4 font-medium">
                  {t("type")}
                </th>
                <th scope="col" className="w-20 py-2 text-center font-medium">
                  {t("inApp")}
                </th>
                <th scope="col" className="w-20 py-2 text-center font-medium">
                  {t("email")}
                </th>
              </tr>
            </thead>
            <tbody>
              {prefs.data.map((pref) => (
                <tr key={pref.type} className="border-b border-rule last:border-0">
                  <th scope="row" className="py-3 pr-4 text-left font-normal">
                    <span className="block font-medium text-ink">{t(`types.${pref.type}.label`)}</span>
                    <span className="block text-ink-2">{t(`types.${pref.type}.hint`)}</span>
                  </th>
                  {(["in_app", "email"] as const).map((channel) => (
                    <td key={channel} className="py-3 text-center">
                      <input
                        type="checkbox"
                        checked={pref[channel]}
                        disabled={save.isPending}
                        onChange={(e) => change(pref, channel, e.target.checked)}
                        aria-label={t(channel === "in_app" ? "inAppFor" : "emailFor", { type: t(`types.${pref.type}.label`) })}
                        className="size-4 rounded-chip border-field-edge accent-[var(--color-accent)]"
                      />
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
          {saved === true ? <Alert tone="success">{t("saved")}</Alert> : null}
          {saved === false ? <Alert tone="error">{t("saveFailed")}</Alert> : null}
        </div>
      )}
    </SettingsSection>
  );
}
