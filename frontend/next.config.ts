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
