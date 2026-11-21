import { api, request } from "@/lib/api/client";
import type { AppNotification, NotificationPage, NotificationPreference } from "./types";

/** The list keeps its pagination `meta`, so it reads the whole body rather than only `data`. */
export const fetchNotifications = (page: number, unreadOnly: boolean, perPage: number, signal?: AbortSignal) =>
  request<NotificationPage>("GET", "/api/v1/notifications", { signal, query: { page, per_page: perPage, "filter[unread]": unreadOnly ? 1 : undefined } });

export const fetchUnreadCount = (signal?: AbortSignal) => api.get<{ count: number }>("/api/v1/notifications/unread-count", { signal });

export const markRead = (id: string) => api.post<AppNotification>(`/api/v1/notifications/${encodeURIComponent(id)}/read`);

export const markAllRead = () => api.post<{ count: number }>("/api/v1/notifications/read-all");

export const deleteNotification = (id: string) => api.delete<void>(`/api/v1/notifications/${encodeURIComponent(id)}`);

export const fetchPreferences = (signal?: AbortSignal) => api.get<NotificationPreference[]>("/api/v1/me/notification-preferences", { signal });

export const updatePreferences = (preferences: NotificationPreference[]) =>
  api.put<NotificationPreference[]>("/api/v1/me/notification-preferences", { preferences });
