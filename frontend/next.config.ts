import type { NextConfig } from "next";
import createNextIntlPlugin from "next-intl/plugin";

const withNextIntl = createNextIntlPlugin("./src/i18n/request.ts");

const nextConfig: NextConfig = {
  // Self-contained server bundle for the Docker image (see frontend/Dockerfile).
  output: "standalone",
  // Cache Components is off on purpose: every route depends on request cookies
  // (locale, theme, session) and user data is fetched from the Laravel API.
  // See docs/ARCHITECTURE.md, decision D9.
  poweredByHeader: false,
  async headers() {
    return [
      {
        // Sandboxed 3D scenes (docs/SANDBOXED_3D.md). These documents only ever load inside
        // <iframe sandbox="allow-scripts">, so they run with an opaque origin. The CSP keeps all
        // network access out, including from the worker that runs scene code (blob workers inherit it).
        source: "/sandbox/:path*",
        headers: [
          {
            key: "Content-Security-Policy",
            value: [
              "default-src 'none'",
              "script-src 'self' 'unsafe-eval'",
              "worker-src blob:",
              "style-src 'self'",
              "img-src blob: data:",
              "connect-src 'none'",
              "form-action 'none'",
              "base-uri 'none'",
              "frame-ancestors 'self'",
              "sandbox allow-scripts",
            ].join("; "),
          },
          { key: "Referrer-Policy", value: "no-referrer" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          // The sandboxed document has an opaque origin, so its own scripts count as cross-origin loads.
          // These are public static files with nothing secret in them.
          { key: "Cross-Origin-Resource-Policy", value: "cross-origin" },
        ],
      },
    ];
  },
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default withNextIntl(nextConfig);
