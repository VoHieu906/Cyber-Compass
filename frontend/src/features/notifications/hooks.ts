"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { deleteNotification, fetchNotifications, fetchPreferences, fetchUnreadCount, markAllRead, markRead, updatePreferences } from "./api";
import type { NotificationPreference } from "./types";

export const notificationKeys = {
  all: ["notifications"] as const,
  count: ["notifications", "count"] as const,
  list: (page: number, unreadOnly: boolean, perPage: number) => ["notifications", "list", page, unreadOnly, perPage] as const,
  preferences: ["notifications", "preferences"] as const,
};

/** How often the header asks for the unread count (D34: polling, no websockets). */
export const UNREAD_POLL_MS = 60_000;

/** The unread count, polled every minute while the tab is visible and refreshed on focus. */
export function useUnreadCount(enabled: boolean) {
  return useQuery({
    queryKey: notificationKeys.count,
    queryFn: ({ signal }) => fetchUnreadCount(signal),
    enabled,
    staleTime: 0,
    refetchInterval: UNREAD_POLL_MS,
    refetchIntervalInBackground: false,
    refetchOnWindowFocus: true,
  });
}

export function useNotifications(page: number, unreadOnly: boolean, perPage = 20, enabled = true) {
  return useQuery({
    queryKey: notificationKeys.list(page, unreadOnly, perPage),
    queryFn: ({ signal }) => fetchNotifications(page, unreadOnly, perPage, signal),
    enabled,
    staleTime: 0,
    placeholderData: keepPreviousData,
  });
}

/** Any change refreshes every list and the count, so the bell and the page always agree. */
function useRefresh() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: notificationKeys.all });
}

export function useMarkRead() {
  const refresh = useRefresh();
  return useMutation({ mutationFn: markRead, onSettled: refresh });
}

export function useMarkAllRead() {
  const refresh = useRefresh();
  return useMutation({ mutationFn: markAllRead, onSettled: refresh });
}

export function useDeleteNotification() {
  const refresh = useRefresh();
  return useMutation({ mutationFn: deleteNotification, onSettled: refresh });
}

export function useNotificationPreferences() {
  return useQuery({ queryKey: notificationKeys.preferences, queryFn: ({ signal }) => fetchPreferences(signal) });
}

/** Saves one type's switches; the server returns the full list, which replaces the cache. */
export function useSavePreference() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (preference: NotificationPreference) => updatePreferences([preference]),
    onSuccess: (list) => queryClient.setQueryData(notificationKeys.preferences, list),
  });
}
