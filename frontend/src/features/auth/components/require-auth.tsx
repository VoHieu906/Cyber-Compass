"use client";

import { LoaderCircle } from "lucide-react";
import { useTranslations } from "next-intl";
import { usePathname, useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { useCurrentUser } from "../hooks";

/**
 * Client-side gate for signed-in areas. Laravel issues a session cookie to every
 * visitor, so cookie presence cannot tell guests from learners; the API's /me is
 * the source of truth, and every API route enforces auth again on its own.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
  const t = useTranslations("common");
  const tAuth = useTranslations("auth");
  const tHealth = useTranslations("health");
  const { data: user, isPending, isError, refetch } = useCurrentUser();
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    if (!isPending && !isError && !user) {
      router.replace(`/login?next=${encodeURIComponent(pathname)}`);
    }
  }, [isPending, isError, user, pathname, router]);

  if (isError) {
    return (
      <div className="mx-auto flex max-w-[26rem] flex-col items-start gap-3 py-[var(--space-2xl)]">
        <Alert tone="error">{tAuth("networkError")}</Alert>
        <Button variant="secondary" onClick={() => refetch()}>
          {tHealth("retry")}
        </Button>
      </div>
    );
  }

  if (!user) {
    return (
      <div role="status" className="flex min-h-[40vh] items-center justify-center gap-2 text-muted">
        <LoaderCircle aria-hidden className="size-4 animate-spin" />
        {t("loading")}
      </div>
    );
  }

  return children;
}
