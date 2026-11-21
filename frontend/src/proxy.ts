import { NextResponse, type NextRequest } from "next/server";
import { env } from "@/lib/env";
import { contentSecurityPolicy, originOf } from "@/lib/security/csp";

/**
 * Sets a fresh nonce and the Content Security Policy on every page (D42). Next.js reads the nonce
 * from the request's CSP header and puts it on its own scripts; the root layout passes `x-nonce`
 * to next-themes. CSP_REPORT_ONLY=true sends the policy as report-only, a runtime switch for
 * rolling back without a new image.
 */
export function proxy(request: NextRequest) {
  const nonce = Buffer.from(crypto.randomUUID()).toString("base64");
  const policy = contentSecurityPolicy({
    nonce,
    dev: process.env.NODE_ENV === "development",
    apiOrigin: originOf(env.apiUrl) ?? env.apiUrl,
    mediaOrigin: originOf(process.env.CSP_MEDIA_ORIGIN),
  });
  const header = process.env.CSP_REPORT_ONLY === "true" ? "Content-Security-Policy-Report-Only" : "Content-Security-Policy";

  const requestHeaders = new Headers(request.headers);
  requestHeaders.set("x-nonce", nonce);
  // Next.js looks for the nonce in this request header, whichever header the response uses.
  requestHeaders.set("Content-Security-Policy", policy);

  const response = NextResponse.next({ request: { headers: requestHeaders } });
  response.headers.set(header, policy);
  return response;
}

export const config = {
  matcher: [
    {
      // Pages only: not static files, the image optimizer, the favicon or the 3D sandbox (own CSP).
      source: "/((?!_next/static|_next/image|favicon.ico|sandbox/).*)",
      missing: [
        { type: "header", key: "next-router-prefetch" },
        { type: "header", key: "purpose", value: "prefetch" },
      ],
    },
  ],
};
