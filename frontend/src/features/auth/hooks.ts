"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useLocale } from "next-intl";
import { useTheme } from "next-themes";
import { useRouter } from "next/navigation";
import { setLocale } from "@/i18n/actions";
import { fetchCurrentUser, login, logout, register } from "./api";
import type { User } from "./types";

export const currentUserKey = ["auth", "me"] as const;

export function useCurrentUser() {
  return useQuery({
    queryKey: currentUserKey,
    queryFn: ({ signal }) => fetchCurrentUser(signal),
    staleTime: 5 * 60_000,
  });
}

/**
 * After signing in, the interface follows the account's saved language and theme,
 * so a learner sees the same CyberCompass on every device.
 */
function useApplyUserPreferences() {
  const currentLocale = useLocale();
  const { setTheme } = useTheme();
  const router = useRouter();

  return async (user: User) => {
    setTheme(user.profile.theme);
    if (user.profile.locale !== currentLocale) {
      await setLocale(user.profile.locale);
      router.refresh();
    }
  };
}

export function useLogin() {
  const queryClient = useQueryClient();
  const applyPreferences = useApplyUserPreferences();
  return useMutation({
    mutationFn: login,
    onSuccess: async (user) => {
      queryClient.setQueryData(currentUserKey, user);
      await applyPreferences(user);
    },
  });
}

export function useRegister() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: register,
    onSuccess: (user) => queryClient.setQueryData(currentUserKey, user),
  });
}

export function useLogout() {
  const queryClient = useQueryClient();
  const router = useRouter();
  return useMutation({
    mutationFn: logout,
    onSettled: () => {
      // Drop every cached query: nothing from the previous user may remain on screen.
      queryClient.clear();
      queryClient.setQueryData(currentUserKey, null);
      router.replace("/login");
    },
  });
}
