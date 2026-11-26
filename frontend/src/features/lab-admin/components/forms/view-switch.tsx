"use client";

import { useTranslations } from "next-intl";
import { SegmentedControl } from "@/components/ui/segmented-control";

export type View = "form" | "json";

/**
 * Form or JSON for the same data. Both read and write the one definition, so switching never loses
 * anything; a JSON box that does not parse yet holds the switch (its text is not data until it parses).
 */
export function ViewSwitch({
  view,
  onChange,
  blocked,
  label,
}: {
  view: View;
  onChange: (v: View) => void;
  blocked?: boolean;
  label: string;
}) {
  const t = useTranslations("labAdmin.forms");
  return (
    <div className="flex flex-wrap items-center gap-3">
      <SegmentedControl<View>
        label={label}
        value={view}
        onChange={onChange}
        disabled={blocked}
        options={[
          { value: "form", label: t("viewForm") },
          { value: "json", label: t("viewJson") },
        ]}
      />
      {blocked ? <p className="text-xs text-bad">{t("fixJsonFirst")}</p> : null}
    </div>
  );
}
