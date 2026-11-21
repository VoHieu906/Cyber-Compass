"use client";

import { Bell, CircleCheck, CircleDot, ClipboardCheck, Flag, Sparkles } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import type { AppNotification, NotificationType } from "../types";

export const NOTIFICATION_ICON: Record<NotificationType, typeof Bell> = {
  recommendation: Sparkles,
  lesson_completed: CircleCheck,
  milestone: Flag,
  reminder: Bell,
  assessment_result: ClipboardCheck,
};

/** The sentence for a notification, built from its type and parameters in the current language. */
export function useNotificationText() {
  const t = useTranslations("notifications.items");
  return (n: AppNotification): string => {
    const p = n.params;
    switch (n.type) {
      case "recommendation":
        return t("recommendation", { lesson: String(p.lesson ?? ""), context: String(p.context ?? "") });
      case "lesson_completed":
        return t("lessonCompleted", { lesson: String(p.lesson ?? "") });
      case "assessment_result":
        return t("assessmentResult", { score: Number(p.score ?? 0) });
      case "reminder":
        return Number(p.streak ?? 0) > 0 ? t("reminderStreak", { days: Number(p.streak) }) : t("reminder");
      case "milestone":
        if (p.kind === "course") return t("milestoneCourse", { title: String(p.title ?? "") });
        if (p.kind === "roadmap") return t("milestoneRoadmap", { percent: Number(p.percent ?? 0), title: String(p.title ?? "") });
        return t("milestoneStreak", { days: Number(p.days ?? 0) });
    }
  };
}

/** "New" as words, plus the relative time; never a bare coloured dot. */
export function NotificationMeta({ notification }: { notification: AppNotification }) {
  const t = useTranslations("notifications");
  const format = useFormatter();
  return (
    <span className="flex flex-wrap items-center gap-x-2 text-sm text-muted">
      {notification.read_at === null ? (
        <span className="inline-flex items-center gap-1 font-medium text-ink">
          <CircleDot aria-hidden className="size-3.5 text-accent" />
          {t("unread")}
        </span>
      ) : null}
      <time dateTime={notification.created_at}>{format.relativeTime(new Date(notification.created_at), new Date())}</time>
    </span>
  );
}
