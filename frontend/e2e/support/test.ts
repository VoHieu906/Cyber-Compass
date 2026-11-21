import { test as base, expect, type Page } from "@playwright/test";
import type { Account } from "./accounts";

/**
 * The suite's `test`: every page fails its test on a console error or an uncaught exception.
 * Signed-out `/me` checks log a 401 by design; a spec that expects other messages (for example a
 * lab's 409) matches them with `allowedConsole`: one RegExp, since Playwright would read an array
 * of RegExps as its own [value, options] pair.
 */
export const test = base.extend<{ allowedConsole: RegExp | null; consoleGuard: void }>({
  allowedConsole: [null, { option: true }],
  consoleGuard: [
    async ({ page, allowedConsole }, use) => {
      const errors: string[] = [];
      const allowed = [/status of 401/, ...(allowedConsole ? [allowedConsole] : [])];
      page.on("console", (m) => {
        if (m.type() === "error" && !allowed.some((r) => r.test(m.text()))) errors.push(`${page.url()}: ${m.text()}`);
      });
      page.on("pageerror", (e) => errors.push(`${page.url()}: ${e.message}`));
      await use();
      expect(errors, "console errors").toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };

/** Signs in through the login form and waits for the dashboard (D35). */
export async function login(page: Page, { email, password }: Account, next?: string) {
  await page.goto(next ? `/login?next=${encodeURIComponent(next)}` : "/login");
  await page.getByLabel("Email").fill(email);
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Log in", exact: true }).click();
  await page.waitForURL(next ?? "/dashboard");
}

/** Pixels the page is wider than the viewport (0 when it fits). */
export function pageOverflow(page: Page) {
  return page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
}

/** Pixels the header's content is wider than the header (0 when it fits; Phase 6 Part E). */
export function headerOverflow(page: Page) {
  return page.evaluate(() => {
    const bar = document.querySelector("header > div");
    return bar ? bar.scrollWidth - bar.clientWidth : 0;
  });
}

/** Markdown-style inline marks (`code`, **bold**) are rendered, so visible text drops them. */
export function plain(text: string) {
  return text.replace(/`|\*\*|\*/g, "");
}

const API_URL = process.env.E2E_API_URL ?? "http://localhost:8000";

/**
 * A GET to the API as the signed-in page (its cookies). Sanctum treats a request as the SPA's only
 * when it names the app as its origin, as the browser does.
 */
export async function apiGet<T>(page: Page, path: string): Promise<T> {
  const origin = new URL(page.url()).origin;
  const response = await page.request.get(`${API_URL}${path}`, {
    headers: { Accept: "application/json", Origin: origin, Referer: `${origin}/` },
  });
  expect(response.status(), `GET ${path}`).toBe(200);
  return ((await response.json()) as { data: T }).data;
}
