"use client";

import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import type { LessonStatus } from "../types";

const chip = "inline-flex items-center gap-1.5 rounded-chip border px-2 py-0.5 text-xs font-medium whitespace-nowrap";

/** Published is the normal state, so it is quiet; draft and archived stand out. */
export function LessonStatusBadge({ status }: { status: LessonStatus }) {
  const t = useTranslations("content.status");
  return (
    <span
      className={cn(
        chip,
        status === "published" && "border-transparent px-0 text-muted",
        status === "draft" &&
          "border-[color-mix(in_oklab,var(--color-accent)_45%,var(--color-rule))] bg-accent-soft text-ink",
        status === "archived" && "border-rule bg-paper-3 text-muted",
      )}
    >
      <span
        aria-hidden
        className={cn(
          "size-1.5 rounded-full",
          status === "published" ? "bg-ok" : status === "draft" ? "bg-accent" : "bg-muted",
        )}
      />
      {t(status)}
    </span>
  );
}

/** Work an admin has saved but not published yet. Amber: it needs attention before learners see it. */
export function UnpublishedChip() {
  const t = useTranslations("content.status");
  return (
    <span
      className={cn(chip, "border-[color-mix(in_oklab,var(--color-attn)_45%,var(--color-rule))] bg-attn-soft text-ink")}
    >
      {t("unpublished")}
    </span>
  );
}
