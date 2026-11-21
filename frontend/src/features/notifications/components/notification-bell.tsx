"use client";

import { Bell, LoaderCircle } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useId, useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { useCurrentUser } from "@/features/auth/hooks";
import { cn } from "@/lib/utils";
import { useMarkAllRead, useMarkRead, useNotifications, useUnreadCount } from "../hooks";
import { NOTIFICATION_ICON, NotificationMeta, useNotificationText } from "./notification-item";

const PREVIEW = 5;

/**
 * Header bell for signed-in learners (D34): the unread count, polled every minute, and a panel
 * with the latest notifications. Opening an item marks it read. Guests see nothing.
 */
export function NotificationBell() {
  const t = useTranslations("notifications");
  const { data: user } = useCurrentUser();
  const count = useUnreadCount(Boolean(user)).data?.count ?? 0;
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const panelId = useId();

  useEffect(() => {
    if (!open) return;
    const onPointer = (e: PointerEvent) => {
      if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  if (!user) return null;

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        aria-expanded={open}
        aria-controls={panelId}
        aria-label={count ? t("bellUnread", { count }) : t("bell")}
        onClick={() => setOpen((v) => !v)}
        className="relative flex size-9 items-center justify-center rounded-control text-ink-2 transition-[color,background-color] duration-[var(--dur-micro)] hover:bg-paper-3 hover:text-ink active:bg-paper-2"
      >
        <Bell aria-hidden className="size-[18px]" />
        {count ? (
          <span aria-hidden className="measured absolute -right-0.5 -top-0.5 min-w-[1.125rem] rounded-full bg-accent px-1 text-center text-[11px] font-semibold leading-[1.125rem] text-accent-ink">
            {count > 9 ? "9+" : count}
          </span>
        ) : null}
      </button>
      <div
        id={panelId}
        hidden={!open}
        className="absolute right-0 top-[calc(100%+8px)] z-[var(--z-dropdown)] w-[min(22rem,calc(100vw-2*var(--gutter)))] rounded-card border border-rule bg-paper-2 shadow-[var(--shadow-overlay)] max-sm:fixed max-sm:inset-x-[var(--gutter)] max-sm:top-[calc(var(--header-height)+8px)] max-sm:w-auto"
      >
        {open ? <BellPanel close={() => setOpen(false)} /> : null}
      </div>
    </div>
  );
}

function BellPanel({ close }: { close: () => void }) {
  const t = useTranslations("notifications");
  const text = useNotificationText();
  const list = useNotifications(1, false, PREVIEW);
  const markRead = useMarkRead();
  const markAll = useMarkAllRead();
  const hasUnread = list.data?.data.some((n) => n.read_at === null) ?? false;

  return (
    <div className="flex flex-col">
      <div className="flex items-center justify-between gap-3 border-b border-rule px-4 py-3">
        <h2 className="text-sm font-semibold text-ink">{t("title")}</h2>
        {hasUnread ? (
          <button type="button" onClick={() => markAll.mutate()} disabled={markAll.isPending} className="text-sm text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent disabled:opacity-50">
            {t("markAllRead")}
          </button>
        ) : null}
      </div>
      {list.isError ? (
        <div className="p-4">
          <Alert tone="error">{t("loadError")}</Alert>
        </div>
      ) : !list.data ? (
        <p role="status" className="flex h-40 items-center justify-center gap-2 text-sm text-muted">
          <LoaderCircle aria-hidden className="size-4 animate-spin motion-reduce:animate-none" />
          {t("loading")}
        </p>
      ) : list.data.data.length === 0 ? (
        <p className="px-4 py-6 text-sm text-ink-2">{t("empty")}</p>
      ) : (
        <ul className="flex max-h-[min(26rem,60vh)] flex-col divide-y divide-rule overflow-y-auto">
          {list.data.data.map((n) => {
            const Icon = NOTIFICATION_ICON[n.type];
            return (
              <li key={n.id} className={cn("flex gap-3 px-4 py-3", n.read_at === null && "bg-accent-soft/40")}>
                <Icon aria-hidden className="mt-0.5 size-4 shrink-0 text-muted" />
                <div className="flex min-w-0 flex-col gap-1">
                  <Link
                    href={n.url ?? "/notifications"}
                    onClick={() => {
                      if (n.read_at === null) markRead.mutate(n.id);
                      close();
                    }}
                    className="text-sm text-ink hover:underline hover:decoration-accent hover:underline-offset-4"
                  >
                    {text(n)}
                  </Link>
                  <NotificationMeta notification={n} />
                </div>
              </li>
            );
          })}
        </ul>
      )}
      <Link href="/notifications" onClick={close} className="border-t border-rule px-4 py-3 text-center text-sm font-medium text-ink hover:bg-paper-3">
        {t("seeAll")}
      </Link>
    </div>
  );
}
