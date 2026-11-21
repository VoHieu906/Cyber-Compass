import { defineConfig, devices } from "@playwright/test";

/**
 * End-to-end suite (Phase 7 Part A, ARCHITECTURE D45). It drives the real stack: the Laravel API
 * in Docker and the Next.js app on port 3000 (an already running `npm run dev` is reused;
 * otherwise a production build is started). Accounts are throwaway `e2e-*@example.test` users
 * made by `php artisan cybercompass:e2e-users` and purged after the run.
 *
 *   npm run e2e                 Edge locally (no browser download)
 *   E2E_CHANNEL= npm run e2e    Playwright's bundled Chromium (CI)
 */
const baseURL = process.env.E2E_BASE_URL ?? "http://localhost:3000";
const channel = process.env.E2E_CHANNEL ?? "msedge";
const browser = channel ? { channel } : {};

export default defineConfig({
  testDir: "./e2e",
  globalSetup: "./e2e/support/global-setup.ts",
  globalTeardown: "./e2e/support/global-teardown.ts",
  // Specs use separate accounts, so they can run side by side; two workers keep a dev server responsive.
  workers: process.env.CI ? 1 : 2,
  retries: 0,
  timeout: 90_000,
  expect: { timeout: 15_000 },
  forbidOnly: Boolean(process.env.CI),
  reporter: process.env.CI ? [["list"], ["html", { open: "never" }]] : [["list"]],
  use: {
    baseURL,
    locale: "en-US",
    timezoneId: "Asia/Ho_Chi_Minh",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "desktop",
      testIgnore: /\.phone\.spec\.ts$/,
      use: { ...devices["Desktop Chrome"], ...browser, viewport: { width: 1280, height: 900 } },
    },
    {
      name: "phone",
      testMatch: /\.phone\.spec\.ts$/,
      // Chromium-based phone profile: Edge and Chromium only emulate Chromium devices.
      use: { ...devices["Pixel 7"], ...browser, viewport: { width: 390, height: 844 } },
    },
  ],
  // Another stack (E2E_BASE_URL, e.g. docker-compose.prod.yml on :3100) is already running.
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : { command: "npm run build && npm run start", url: baseURL, reuseExistingServer: true, timeout: 300_000 },
});
