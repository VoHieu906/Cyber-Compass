"use client";

import { useCallback, useSyncExternalStore, type ReactNode } from "react";
import { useCurrentUser } from "@/features/auth/hooks";
import { useUpdatePreferences } from "@/features/profile/hooks";
import type { Overrides } from "@/lib/shortcuts/registry";
import { ShortcutOverridesContext } from "@/lib/shortcuts/use-shortcut";

/**
 * The compass switch and shortcut overrides (D53): on the account once signed in, in this
 * browser's localStorage for guests. Storage can be full or blocked, so every access is guarded.
 */
type GuestPrefs = { compass_enabled?: boolean; shortcuts?: Overrides };

const STORAGE_KEY = "cybercompass:prefs";
const CHANGED = "cybercompass:prefs-changed";
const EMPTY: GuestPrefs = {};

let cache: { raw: string | null; value: GuestPrefs } = { raw: null, value: EMPTY };

function readGuest(): GuestPrefs {
  let raw: string | null = null;
  try {
    raw = localStorage.getItem(STORAGE_KEY);
  } catch {
    return EMPTY;
  }
  if (raw !== cache.raw) {
    let value = EMPTY;
    try {
      const parsed: unknown = raw ? JSON.parse(raw) : null;
      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) value = parsed as GuestPrefs;
    } catch {
      // A broken value falls back to the defaults.
    }
    cache = { raw, value };
  }
  return cache.value;
}

function writeGuest(change: GuestPrefs) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...readGuest(), ...change }));
  } catch {
    return false;
  }
  window.dispatchEvent(new Event(CHANGED));
  return true;
}

function subscribe(onChange: () => void) {
  window.addEventListener(CHANGED, onChange);
  window.addEventListener("storage", onChange);
  return () => {
    window.removeEventListener(CHANGED, onChange);
    window.removeEventListener("storage", onChange);
  };
}

const useGuestPrefs = () => useSyncExternalStore(subscribe, readGuest, () => EMPTY);

/** The preferences in force, plus a save that goes to the account or to this browser. */
export function useNavPrefs() {
  const { data: user, dataUpdatedAt, errorUpdatedAt } = useCurrentUser();
  const guest = useGuestPrefs();
  const update = useUpdatePreferences();
  const save = useCallback(
    async (change: GuestPrefs): Promise<boolean> => {
      if (!user) return writeGuest(change);
      try {
        await update.mutateAsync(change);
        return true;
      } catch {
        return false;
      }
    },
    [update, user],
  );
  const source = user ? user.profile : guest;
  return {
    /**
     * Unknown until /me first answers, so the compass does not flash in. Stays true through
     * refetches: a query that never succeeded goes back to pending on each retry.
     */
    ready: dataUpdatedAt > 0 || errorUpdatedAt > 0,
    compassEnabled: source.compass_enabled ?? true,
    shortcuts: source.shortcuts ?? EMPTY_OVERRIDES,
    save,
    saving: update.isPending,
  };
}

const EMPTY_OVERRIDES: Overrides = {};

export function ShortcutsProvider({ children }: { children: ReactNode }) {
  const { shortcuts } = useNavPrefs();
  return <ShortcutOverridesContext.Provider value={shortcuts}>{children}</ShortcutOverridesContext.Provider>;
}
