"use client";

import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useTransition } from "react";
import { SegmentedControl } from "@/components/ui/segmented-control";
import { useSavePreference } from "@/features/profile/use-save-preference";
import { setLocale } from "@/i18n/actions";
import type { Locale } from "@/i18n/config";

type Props = {
  /** Full language names instead of EN / VI. */
  long?: boolean;
  onSaved?: (ok: boolean) => void;
};

export function LocaleSwitcher({ long = false, onSaved }: Props) {
  const t = useTranslations("preferences");
  const locale = useLocale() as Locale;
  const router = useRouter();
  const { save, signedIn } = useSavePreference();
  const [pending, startTransition] = useTransition();

  function change(next: Locale) {
    if (next === locale) return;
    startTransition(async () => {
      if (signedIn) {
        try {
          await save({ locale: next });
          onSaved?.(true);
        } catch {
          onSaved?.(false);
        }
      }
      await setLocale(next);
      router.refresh();
    });
  }

  return (
    <SegmentedControl<Locale>
      label={t("language")}
      value={locale}
      onChange={change}
      disabled={pending}
      options={[
        { value: "en", label: long ? t("languageEn") : "EN" },
        { value: "vi", label: long ? t("languageVi") : "VI" },
      ]}
    />
  );
}
