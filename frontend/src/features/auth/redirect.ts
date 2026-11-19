/** Where a signed-in learner lands by default. Becomes the dashboard in Phase 6. */
export const HOME_AFTER_LOGIN = "/settings/profile";

/**
 * Only same-site paths are accepted as a post-login destination, so a crafted
 * `?next=https://evil.example` link cannot turn login into an open redirect.
 */
export function safeNext(next: string | null | undefined, fallback = HOME_AFTER_LOGIN): string {
  if (!next || !next.startsWith("/") || next.startsWith("//") || next.startsWith("/\\")) return fallback;
  return next;
}
