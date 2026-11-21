import type { Browser, BrowserContextOptions } from "@playwright/test";
import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

/**
 * Page weight and API traffic per route (Phase 7 Part D, docs/PERFORMANCE.md). Each route is
 * loaded cold, in a fresh context with an empty cache. The numbers mean something only against a
 * production build, so budgets are enforced with E2E_PERF=1 (CI); otherwise the report is written
 * and nothing is asserted.
 */
const ENFORCE = process.env.E2E_PERF === "1";
const API_URL = process.env.E2E_API_URL ?? "http://localhost:8000";

/** Route => budgets: compressed JavaScript in KB, and API requests the page makes on load. */
const BUDGETS: Record<string, { jsKb: number; api: number; signedIn?: boolean }> = {
  "/": { jsKb: 400, api: 3 },
  "/courses": { jsKb: 400, api: 4 },
  "/courses/networking-fundamentals": { jsKb: 400, api: 4 },
  "/careers/soc-analyst": { jsKb: 400, api: 4 },
  "/dashboard": { jsKb: 450, api: 12, signedIn: true },
  "/roadmap": { jsKb: 450, api: 6, signedIn: true },
  "/learn/osi-model": { jsKb: 500, api: 6, signedIn: true },
  "/learn/osi-model/exercise": { jsKb: 500, api: 6, signedIn: true },
  "/labs/investigate-a-suspicious-login": { jsKb: 450, api: 6, signedIn: true },
};

type Row = { route: string; jsKb: number; cssKb: number; requests: number; api: number; ttfbMs: number; lcpMs: number };

async function measure(
  browser: Browser,
  route: string,
  storageState?: BrowserContextOptions["storageState"],
): Promise<Row> {
  const context = await browser.newContext({ storageState, viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  await page.addInitScript(() => {
    const store = window as unknown as { __lcp?: number };
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) store.__lcp = entry.startTime;
    }).observe({ type: "largest-contentful-paint", buffered: true });
  });

  const sizes = { js: 0, css: 0, requests: 0, api: 0 };
  page.on("requestfinished", async (request) => {
    sizes.requests++;
    if (request.url().startsWith(API_URL)) sizes.api++;
    const type = request.resourceType();
    if (type !== "script" && type !== "stylesheet") return;
    const { responseBodySize } = await request.sizes();
    if (type === "script") sizes.js += responseBodySize;
    else sizes.css += responseBodySize;
  });

  await page.goto(route, { waitUntil: "networkidle" });
  await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
  await page.waitForTimeout(500);
  const timing = await page.evaluate(() => {
    const nav = performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming;
    return { ttfb: nav.responseStart, lcp: (window as unknown as { __lcp?: number }).__lcp ?? 0 };
  });
  await context.close();

  return {
    route,
    jsKb: Math.round(sizes.js / 1024),
    cssKb: Math.round(sizes.css / 1024),
    requests: sizes.requests,
    api: sizes.api,
    ttfbMs: Math.round(timing.ttfb),
    lcpMs: Math.round(timing.lcp),
  };
}

test("pages stay within their weight and API budgets", async ({ browser, page }, testInfo) => {
  test.setTimeout(180_000);
  await login(page, account("perf"));
  const signedIn = await page.context().storageState();

  const rows: Row[] = [];
  for (const [route, budget] of Object.entries(BUDGETS)) {
    rows.push(await measure(browser, route, budget.signedIn ? signedIn : undefined));
  }

  const table = rows.map(
    (r) =>
      `${r.route.padEnd(40)} js ${String(r.jsKb).padStart(4)} KB  css ${String(r.cssKb).padStart(3)} KB  requests ${String(r.requests).padStart(3)}  api ${String(r.api).padStart(2)}  ttfb ${String(r.ttfbMs).padStart(5)} ms  lcp ${String(r.lcpMs).padStart(5)} ms`,
  );
  console.log(`\nPerformance (${ENFORCE ? "production build, budgets enforced" : "report only"})\n${table.join("\n")}`);
  await testInfo.attach("performance.json", { body: JSON.stringify(rows, null, 2), contentType: "application/json" });

  if (!ENFORCE) return;
  for (const row of rows) {
    const budget = BUDGETS[row.route];
    expect(row.jsKb, `${row.route} JavaScript`).toBeLessThanOrEqual(budget.jsKb);
    expect(row.api, `${row.route} API requests`).toBeLessThanOrEqual(budget.api);
  }
  // The `api` limit is 120 a minute per learner; a dashboard load plus a minute of bell polling
  // (one request) must leave room for many page loads.
  const dashboard = rows.find((r) => r.route === "/dashboard")!;
  expect(dashboard.api + 1).toBeLessThanOrEqual(120 / 8);
});
