"use client";

import { useTranslations } from "next-intl";
import { useEffect, useMemo, useRef } from "react";
import { SelectField } from "@/components/ui/input";
import { useCurrentUser } from "@/features/auth/hooks";
import { useSaveTimezone } from "../hooks";

/** The browser's IANA timezone, or null where the platform does not report one. */
export function browserTimezone(): string | null {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || null;
  } catch {
    return null;
  }
}

function knownTimezones(): string[] {
  try {
    return Intl.supportedValuesOf("timeZone");
  } catch {
    return [];
  }
}

/**
 * Fills in the learner's timezone from the browser the first time it is missing (D33). Renders
 * nothing; a timezone chosen in settings is never overwritten.
 */
export function TimezoneSync() {
  const { data: user } = useCurrentUser();
  const save = useSaveTimezone();
  const sent = useRef(false);

  useEffect(() => {
    const timezone = browserTimezone();
    if (!user || user.profile.timezone !== null || !timezone || sent.current) return;
    sent.current = true;
    save.mutate(timezone);
  }, [user, save]);

  return null;
}

/** Settings: the timezone that decides what "today" and "this week" mean. Saves on change. */
export function TimezoneField({ onSaved }: { onSaved: (ok: boolean) => void }) {
  const t = useTranslations("settings.preferences");
  const { data: user } = useCurrentUser();
  const save = useSaveTimezone();
  const detected = browserTimezone();
  const current = user?.profile.timezone ?? detected ?? "UTC";
  const options = useMemo(() => {
    const zones = new Set([...knownTimezones(), current, "UTC"]);
    return [...zones].sort().map((zone) => ({ value: zone, label: zone.replaceAll("_", " ") }));
  }, [current]);

  return (
    <SelectField
      label={t("timezone")}
      hint={detected && detected !== current ? t("timezoneDetected", { timezone: detected.replaceAll("_", " ") }) : undefined}
      options={options}
      value={current}
      disabled={save.isPending}
      onChange={(event) => save.mutate(event.target.value, { onSuccess: () => onSaved(true), onError: () => onSaved(false) })}
      className="max-w-[22rem]"
    />
  );
}
