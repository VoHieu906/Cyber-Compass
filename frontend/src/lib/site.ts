import "server-only";

/**
 * The public address of the app (SITE_URL, server-only, read at runtime), for canonical links and
 * Open Graph URLs. Not the request's Host: behind CloudFront that is the origin's name (D41).
 */
export const siteUrl = () => new URL(process.env.SITE_URL ?? "http://localhost:3000");
