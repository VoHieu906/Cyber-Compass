/**
 * The app's Content Security Policy (ARCHITECTURE D42, docs/SECURITY_REVIEW.md). Scripts run only
 * with this request's nonce ('strict-dynamic' lets them load the chunks they need); there is no
 * 'unsafe-inline' for scripts. Inline style attributes stay allowed: graphs, charts and the editor
 * position elements with them, and a nonce cannot cover a style attribute.
 *
 * /sandbox (the 3D runtime) is not covered here: it has its own, stricter policy (D22).
 */
export type CspOptions = {
  nonce: string;
  /** `next dev` needs eval for React's error overlays. */
  dev: boolean;
  /** Origin of the Laravel API (fetches, avatars on the local disk). */
  apiOrigin: string;
  /** Origin that serves uploads in production (CloudFront in front of S3, D41), if separate. */
  mediaOrigin?: string;
};

export function contentSecurityPolicy({ nonce, dev, apiOrigin, mediaOrigin }: CspOptions): string {
  const media = mediaOrigin ? ` ${mediaOrigin}` : "";
  const directives = [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${dev ? " 'unsafe-eval'" : ""}`,
    "style-src 'self' 'unsafe-inline'",
    `img-src 'self' blob: data: ${apiOrigin}${media}`,
    "font-src 'self'",
    `connect-src 'self' ${apiOrigin}`,
    "frame-src 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ];
  // Only over HTTPS: locally the API is plain http://localhost:8000 and must stay reachable.
  if (apiOrigin.startsWith("https://")) directives.push("upgrade-insecure-requests");
  return directives.join("; ");
}

/** The origin part of a URL ("https://api.example.org"), or undefined when it isn't a URL. */
export function originOf(url: string | undefined): string | undefined {
  if (!url) return undefined;
  try {
    return new URL(url).origin;
  } catch {
    return undefined;
  }
}
