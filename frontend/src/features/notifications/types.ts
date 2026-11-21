/** The notification types (D34); each can be switched per channel in settings. */
export const NOTIFICATION_TYPES = ["recommendation", "lesson_completed", "milestone", "reminder", "assessment_result"] as const;
export type NotificationType = (typeof NOTIFICATION_TYPES)[number];

/**
 * A notification as stored: a type, its parameters and a path in the app. The words are built
 * here, in the current language (see `useNotificationText`).
 */
export type AppNotification = {
  id: string;
  type: NotificationType;
  params: Record<string, string | number | null>;
  url: string | null;
  read_at: string | null;
  created_at: string;
};

export type NotificationPage = {
  data: AppNotification[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export type NotificationPreference = { type: NotificationType; in_app: boolean; email: boolean };
