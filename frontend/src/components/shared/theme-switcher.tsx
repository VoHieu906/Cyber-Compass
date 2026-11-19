"use client";

import { Monitor, Moon, Sun } from "lucide-react";
import { useTranslations } from "next-intl";
import { useTheme } from "next-themes";
import { useSyncExternalStore } from "react";
import { SegmentedControl } from "@/components/ui/segmented-control";

type ThemeValue = "light" | "dark" | "system";

const noop = () => () => {};

export function ThemeSwitcher({ iconOnly = true }: { iconOnly?: boolean }) {
  const t = useTranslations("preferences");
  const { theme, setTheme } = useTheme();
  // The stored theme is only known on the client; render a neutral state until hydrated.
  const mounted = useSyncExternalStore(noop, () => true, () => false);

  return (
    <SegmentedControl<ThemeValue>
      label={t("theme")}
      iconOnly={iconOnly}
      value={mounted ? (theme as ThemeValue) : undefined}
      onChange={setTheme}
      options={[
        { value: "light", label: t("themeLight"), icon: <Sun aria-hidden /> },
        { value: "dark", label: t("themeDark"), icon: <Moon aria-hidden /> },
        { value: "system", label: t("themeSystem"), icon: <Monitor aria-hidden /> },
      ]}
    />
  );
}
