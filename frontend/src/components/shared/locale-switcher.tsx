"use client";

import { useLocale, useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useTransition } from "react";
import { SegmentedControl } from "@/components/ui/segmented-control";
import { setLocale } from "@/i18n/actions";
import type { Locale } from "@/i18n/config";

export function LocaleSwitcher() {
  const t = useTranslations("preferences");
  const locale = useLocale() as Locale;
  const router = useRouter();
  const [pending, startTransition] = useTransition();

  function change(next: Locale) {
    if (next === locale) return;
    startTransition(async () => {
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
        { value: "en", label: "EN" },
        { value: "vi", label: "VI" },
      ]}
    />
  );
}
