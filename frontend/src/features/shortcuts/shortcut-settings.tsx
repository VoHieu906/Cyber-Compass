"use client";

import { useTranslations } from "next-intl";
import { useState, useSyncExternalStore, type KeyboardEvent } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useCurrentUser } from "@/features/auth/hooks";
import { SettingsSection } from "@/features/profile/components/settings-section";
import { ACTION_IDS, ACTIONS, bindingFromEvent, bindingsFor, findConflict, isMacPlatform, keyParts, refusal, type ActionId, type Binding, type ShortcutGroup } from "@/lib/shortcuts/registry";
import { useNavPrefs } from "./prefs";

const GROUPS: ShortcutGroup[] = ["general", "search", "editor", "compass", "courseMap"];
const noop = () => () => {};

function Keys({ binding, mac }: { binding: Binding; mac: boolean }) {
  return (
    <span className="inline-flex items-center gap-1">
      {keyParts(binding, mac).map((part, i) => (
        <kbd key={i} className="rounded-chip border border-field-edge bg-paper-2 px-1.5 py-0.5 font-mono text-xs text-ink">
          {part}
        </kbd>
      ))}
    </span>
  );
}

/**
 * Settings → Keyboard shortcuts (D52): every action in the registry, grouped. Rebindable
 * actions can be changed (with conflict and reserved-key checks), turned off and reset; the
 * menu keys are listed only.
 */
export function ShortcutSettings() {
  const t = useTranslations("shortcuts");
  const { data: user } = useCurrentUser();
  const { shortcuts, save, saving } = useNavPrefs();
  const mac = useSyncExternalStore(noop, isMacPlatform, () => false);
  const [recording, setRecording] = useState<ActionId | null>(null);
  const [message, setMessage] = useState<{ tone: "success" | "error"; text: string } | null>(null);

  async function store(next: typeof shortcuts) {
    setMessage((await save({ shortcuts: next })) ? { tone: "success", text: t("saved") } : { tone: "error", text: t("saveFailed") });
  }

  function record(id: ActionId, e: KeyboardEvent<HTMLButtonElement>) {
    // Nothing else reacts to the keys while recording, not even the shortcuts being changed.
    e.preventDefault();
    e.stopPropagation();
    if (e.key === "Escape" && !e.ctrlKey && !e.altKey && !e.metaKey && !e.shiftKey) {
      setRecording(null);
      return;
    }
    const binding = bindingFromEvent(e.nativeEvent, mac);
    if (!binding) return;
    const keys = keyParts(binding, mac).join(mac ? "" : "+");
    const refused = refusal(binding);
    if (refused) {
      setMessage({ tone: "error", text: t(refused, { keys }) });
      return;
    }
    const other = findConflict(id, binding, shortcuts, mac);
    if (other) {
      setMessage({ tone: "error", text: t("conflict", { keys, action: t(`actions.${other}`) }) });
      return;
    }
    setRecording(null);
    void store({ ...shortcuts, [id]: binding });
  }

  function without(id: ActionId) {
    const next = { ...shortcuts };
    delete next[id];
    return next;
  }

  const overridden = ACTION_IDS.some((id) => id in shortcuts);
  // The lesson editor is an admin tool; learners never meet its shortcut.
  const groups = GROUPS.filter((g) => g !== "editor" || user?.role === "admin");

  return (
    <div className="flex flex-col gap-8">
      <p className="text-sm">{t("lede")}</p>
      {groups.map((group) => (
        <SettingsSection key={group} title={t(`groups.${group}`)}>
          <ul className="flex flex-col divide-y divide-rule">
            {ACTION_IDS.filter((id) => ACTIONS[id].group === group).map((id) => {
              const action: (typeof ACTIONS)[ActionId] = ACTIONS[id];
              const fixed = "fixed" in action;
              const bindings = bindingsFor(id, shortcuts);
              const label = t(`actions.${id}`);
              return (
                <li key={id} className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3">
                  <span className="text-sm text-ink">{label}</span>
                  <span className="flex flex-wrap items-center gap-2">
                    {recording === id ? (
                      <button
                        type="button"
                        autoFocus
                        onKeyDown={(e) => record(id, e)}
                        onBlur={() => setRecording(null)}
                        aria-label={t("recordingFor", { action: label })}
                        className="rounded-control border border-accent bg-accent-soft px-3 py-1.5 text-sm text-ink"
                      >
                        {t("recording")}
                      </button>
                    ) : bindings.length === 0 ? (
                      <span className="text-sm text-muted">{t("off")}</span>
                    ) : (
                      bindings.map((b, i) => (
                        <span key={i} className="inline-flex items-center gap-2">
                          {i > 0 ? <span className="text-xs text-muted">{t("or")}</span> : null}
                          <Keys binding={b} mac={mac} />
                        </span>
                      ))
                    )}
                    {fixed ? null : (
                      <>
                        <Button size="sm" variant="secondary" disabled={saving} onClick={() => setRecording(id)} aria-label={t("changeFor", { action: label })}>
                          {t("change")}
                        </Button>
                        {bindings.length > 0 ? (
                          <Button size="sm" variant="ghost" disabled={saving} onClick={() => void store({ ...shortcuts, [id]: null })} aria-label={t("disableFor", { action: label })}>
                            {t("disable")}
                          </Button>
                        ) : null}
                        {id in shortcuts ? (
                          <Button size="sm" variant="ghost" disabled={saving} onClick={() => void store(without(id))} aria-label={t("resetFor", { action: label })}>
                            {t("reset")}
                          </Button>
                        ) : null}
                      </>
                    )}
                  </span>
                </li>
              );
            })}
            {group === "compass" ? (
              <>
                <li className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3">
                  <span className="text-sm text-ink">{t("digits")}</span>
                  <Keys binding={{ key: "1–9" }} mac={mac} />
                </li>
                <li className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 py-3">
                  <span className="text-sm text-ink">{t("typeahead")}</span>
                  <Keys binding={{ key: "A–Z" }} mac={mac} />
                </li>
              </>
            ) : null}
          </ul>
          {group === "compass" ? <p className="text-sm text-muted">{t("fixedNote")}</p> : null}
          {group === "courseMap" ? <p className="text-sm text-muted">{t("courseMapNote")}</p> : null}
        </SettingsSection>
      ))}
      <div className="flex flex-col items-start gap-3">
        <Button variant="secondary" disabled={!overridden || saving} onClick={() => void store({})}>
          {t("resetAll")}
        </Button>
        <div aria-live="polite" className="w-full">
          {message ? <Alert tone={message.tone}>{message.text}</Alert> : null}
        </div>
      </div>
    </div>
  );
}
