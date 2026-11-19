"use client";

import { Monitor, Moon, Sun } from "lucide-react";
import { useTranslations } from "next-intl";
import { useTheme } from "next-themes";
import { useSyncExternalStore } from "react";
import { SegmentedControl } from "@/components/ui/segmented-control";
import type { ThemePreference } from "@/features/auth/types";
import { useSavePreference } from "@/features/profile/use-save-preference";

const noop = () => () => {};

type Props = {
  iconOnly?: boolean;
  /** Called with the save result when a signed-in learner changes the theme. */
  onSaved?: (ok: boolean) => void;
};

export function ThemeSwitcher({ iconOnly = true, onSaved }: Props) {
  const t = useTranslations("preferences");
  const { theme, setTheme } = useTheme();
  const { save, signedIn } = useSavePreference();
  // The stored theme is only known on the client; render a neutral state until hydrated.
  const mounted = useSyncExternalStore(noop, () => true, () => false);

  function change(next: ThemePreference) {
    setTheme(next);
    if (signedIn) {
      save({ theme: next }).then(
        () => onSaved?.(true),
        () => onSaved?.(false),
      );
    }
  }

  return (
    <SegmentedControl<ThemePreference>
      label={t("theme")}
      iconOnly={iconOnly}
      value={mounted ? (theme as ThemePreference) : undefined}
      onChange={change}
      options={[
        { value: "light", label: t("themeLight"), icon: <Sun aria-hidden /> },
        { value: "dark", label: t("themeDark"), icon: <Moon aria-hidden /> },
        { value: "system", label: t("themeSystem"), icon: <Monitor aria-hidden /> },
      ]}
    />
  );
}
