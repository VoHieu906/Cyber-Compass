"use client";

import { useCurrentUser } from "@/features/auth/hooks";
import { useUpdatePreferences } from "./hooks";
import type { PreferencesInput } from "./api";

/**
 * Theme and language apply on this device immediately. For a signed-in learner they
 * are also saved to the account, so other devices and emails follow the same choice.
 */
export function useSavePreference() {
  const { data: user } = useCurrentUser();
  const mutation = useUpdatePreferences();
  return {
    save: (input: PreferencesInput) => (user ? mutation.mutateAsync(input) : Promise.resolve(null)),
    status: mutation.status,
    signedIn: Boolean(user),
  };
}
