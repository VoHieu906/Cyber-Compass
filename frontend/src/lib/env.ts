/**
 * Public runtime configuration. NEXT_PUBLIC_* values are inlined at build time,
 * so they must be passed as build args in the Docker image (see frontend/Dockerfile).
 */
export const env = {
  apiUrl: (process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000").replace(/\/+$/, ""),
} as const;
