import "server-only";
import { getLocale } from "next-intl/server";
import { env } from "@/lib/env";
import { ApiError, type ApiErrorBody } from "./client";

/**
 * Server Components fetch public content directly from the API. Inside Docker or a VPC
 * the API has an internal address (INTERNAL_API_URL), which may differ from the URL the
 * browser uses. No cookies are forwarded: these calls only read public data.
 *
 * INTERNAL_API_TOKEN (server-only, never NEXT_PUBLIC_) identifies these reads to the API: on AWS
 * they all come from this server's one address, which must not use up the per-address limit
 * (docs/SECURITY_REVIEW.md S8).
 */
const internalToken = process.env.INTERNAL_API_TOKEN;
const baseUrl = (process.env.INTERNAL_API_URL ?? env.apiUrl).replace(/\/+$/, "");

type Options = {
  /** Seconds to reuse the response; public content changes rarely. */
  revalidate?: number;
};

export async function serverGet<T>(path: string, { revalidate = 60 }: Options = {}): Promise<T> {
  const response = await fetch(`${baseUrl}${path}`, {
    headers: {
      Accept: "application/json",
      "Accept-Language": await getLocale(),
      ...(internalToken ? { "X-Internal-Token": internalToken } : {}),
    },
    next: { revalidate },
  });

  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as Partial<ApiErrorBody>;
    throw new ApiError(response.status, body);
  }

  return ((await response.json()) as { data: T }).data;
}

/** Like serverGet, but resolves to null on 404 so pages can call notFound(). */
export async function serverGetOrNull<T>(path: string, options?: Options): Promise<T | null> {
  try {
    return await serverGet<T>(path, options);
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
}
