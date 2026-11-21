"use client";

import { Check, Settings, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { Pagination } from "@/components/ui/pagination";
import { SegmentedControl } from "@/components/ui/segmented-control";
import { cn } from "@/lib/utils";
import { useDeleteNotification, useMarkAllRead, useMarkRead, useNotifications, useUnreadCount } from "../hooks";
import type { AppNotification } from "../types";
import { NOTIFICATION_ICON, NotificationMeta, useNotificationText } from "./notification-item";

type Filter = "all" | "unread";

/** Every notification: filter, mark read, mark all read, delete, page through (D34). */
export function NotificationsView() {
  const t = useTranslations("notifications");
  const [filter, setFilter] = useState<Filter>("all");
  const [page, setPage] = useState(1);
  const list = useNotifications(page, filter === "unread");
  const unread = useUnreadCount(true).data?.count ?? 0;
  const markAll = useMarkAllRead();

  return (
    <div className="container-page flex max-w-[48rem] flex-col gap-6 py-[var(--space-xl)]">
      <header className="flex flex-wrap items-end justify-between gap-4">
        <div className="flex flex-col gap-2">
          <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
          <p className="text-ink-2">{unread ? t("unreadCount", { count: unread }) : t("allRead")}</p>
        </div>
        <Link href="/settings/notifications" className={buttonVariants({ variant: "ghost", size: "sm" })}>
          <Settings aria-hidden />
          {t("settings")}
        </Link>
      </header>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <SegmentedControl
          label={t("filter")}
          options={[
            { value: "all", label: t("filterAll") },
            { value: "unread", label: t("filterUnread") },
          ]}
          value={filter}
          onChange={(value) => {
            setFilter(value);
            setPage(1);
          }}
        />
        <Button variant="secondary" size="sm" onClick={() => markAll.mutate()} disabled={!unread || markAll.isPending}>
          <Check aria-hidden />
          {t("markAllRead")}
        </Button>
      </div>

      {list.isError ? (
        <Alert tone="error">{t("loadError")}</Alert>
      ) : !list.data ? (
        <div role="status" aria-label={t("loading")} className="min-h-[30vh]" />
      ) : list.data.data.length === 0 ? (
        <p className="panel p-6 text-ink-2">{filter === "unread" ? t("emptyUnread") : t("empty")}</p>
      ) : (
        <ul className={cn("panel flex flex-col divide-y divide-rule transition-opacity", list.isPlaceholderData && "opacity-60")}>
          {list.data.data.map((n) => (
            <NotificationRow key={n.id} notification={n} />
          ))}
        </ul>
      )}

      {list.data && list.data.meta.last_page > 1 ? (
        <Pagination
          page={list.data.meta.current_page}
          totalPages={list.data.meta.last_page}
          onChange={(p) => {
            setPage(p);
            window.scrollTo?.({ top: 0 });
          }}
          labels={{ nav: t("pagination"), previous: t("previous"), next: t("next"), page: t("page", { page: list.data.meta.current_page, total: list.data.meta.last_page }) }}
        />
      ) : null}
    </div>
  );
}

function NotificationRow({ notification: n }: { notification: AppNotification }) {
  const t = useTranslations("notifications");
  const text = useNotificationText();
  const markRead = useMarkRead();
  const remove = useDeleteNotification();
  const Icon = NOTIFICATION_ICON[n.type];
  const unread = n.read_at === null;
  const label = text(n);

  return (
    <li className={cn("flex items-start gap-3 px-5 py-4", unread && "bg-accent-soft/40")}>
      <Icon aria-hidden className="mt-0.5 size-5 shrink-0 text-muted" />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        {n.url ? (
          <Link
            href={n.url}
            onClick={() => unread && markRead.mutate(n.id)}
            className={cn("text-ink hover:underline hover:decoration-accent hover:underline-offset-4", unread && "font-medium")}
          >
            {label}
          </Link>
        ) : (
          <span className={cn("text-ink", unread && "font-medium")}>{label}</span>
        )}
        <NotificationMeta notification={n} />
      </div>
      <div className="flex shrink-0 items-center gap-1">
        {unread ? (
          <Button variant="ghost" size="sm" onClick={() => markRead.mutate(n.id)} disabled={markRead.isPending} aria-label={t("markReadOf", { text: label })}>
            <Check aria-hidden />
            <span className="max-sm:sr-only">{t("markRead")}</span>
          </Button>
        ) : null}
        <Button variant="ghost" size="sm" onClick={() => remove.mutate(n.id)} disabled={remove.isPending} aria-label={t("deleteOf", { text: label })}>
          <Trash2 aria-hidden />
        </Button>
      </div>
    </li>
  );
}
