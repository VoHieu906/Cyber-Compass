"use client";

import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import type { AccountStatus, Role } from "../types";

const chip = "inline-flex items-center gap-1.5 rounded-chip border px-2 py-0.5 text-xs font-medium whitespace-nowrap";

/** Admin is the privileged role, so it carries the attention colour; learners stay neutral. */
export function RoleBadge({ role }: { role: Role }) {
  const t = useTranslations("admin.roles");
  return (
    <span
      className={cn(
        chip,
        role === "admin"
          ? "border-[color-mix(in_oklab,var(--color-attn)_45%,var(--color-rule))] bg-attn-soft text-ink"
          : "border-rule text-muted",
      )}
    >
      {t(role)}
    </span>
  );
}

export function StatusBadge({ status }: { status: AccountStatus }) {
  const t = useTranslations("admin.status");
  return (
    <span
      className={cn(
        chip,
        status === "suspended"
          ? "border-[color-mix(in_oklab,var(--color-bad)_45%,var(--color-rule))] bg-bad-soft text-ink"
          : "border-transparent px-0 text-muted",
      )}
    >
      <span aria-hidden className={cn("size-1.5 rounded-full", status === "suspended" ? "bg-bad" : "bg-ok")} />
      {t(status)}
    </span>
  );
}
