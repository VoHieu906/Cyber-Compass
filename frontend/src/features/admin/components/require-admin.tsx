"use client";

import { ShieldOff } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import type { ReactNode } from "react";
import { buttonVariants } from "@/components/ui/button";
import { useCurrentUser } from "@/features/auth/hooks";

/**
 * Renders the admin console only for admins. Used inside RequireAuth, so a user is
 * always loaded here. This is a courtesy for the interface: every admin API route
 * checks the role again on the server (EnsureAdmin plus a policy per action).
 */
export function RequireAdmin({ children }: { children: ReactNode }) {
  const t = useTranslations("admin.denied");
  const { data: user } = useCurrentUser();

  if (user?.role !== "admin") {
    return (
      <div className="container-page py-[var(--space-2xl)]">
        <div className="panel flex max-w-[34rem] flex-col items-start gap-3 p-8">
          <ShieldOff aria-hidden className="size-6 text-muted" />
          <h1 className="text-[length:var(--text-xl)]">{t("title")}</h1>
          <p>{t("body")}</p>
          <Link href="/courses" className={buttonVariants({ variant: "secondary", size: "sm" })}>
            {t("back")}
          </Link>
        </div>
      </div>
    );
  }

  return children;
}
