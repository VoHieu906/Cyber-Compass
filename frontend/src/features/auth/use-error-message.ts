"use client";

import { useTranslations } from "next-intl";
import { ApiError } from "@/lib/api/client";

/**
 * A user-facing message for a failed request: the first server validation message,
 * the server's own message for client errors, or a network/generic fallback.
 * Server error text for 5xx is never shown (it is generic anyway).
 */
export function useErrorMessage() {
  const t = useTranslations("auth");
  return (error: unknown): string => {
    if (!(error instanceof ApiError)) return t("genericError");
    if (error.status === 0) return t("networkError");
    const firstFieldMessage = Object.values(error.fieldErrors)[0]?.[0];
    if (firstFieldMessage) return firstFieldMessage;
    if (error.status < 500 && error.message) return error.message;
    return t("genericError");
  };
}
