"use client";

import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { useCurrentUser } from "../hooks";
import { safeNext } from "../redirect";

/** Sends a signed-in learner away from the login/register screens to where they were going. */
export function GuestOnly({ children }: { children: ReactNode }) {
  const { data: user } = useCurrentUser();
  const router = useRouter();
  const next = safeNext(useSearchParams().get("next"));

  useEffect(() => {
    if (user) router.replace(next);
  }, [user, next, router]);

  return children;
}
