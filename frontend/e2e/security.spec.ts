import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

/**
 * Browser-side security (Phase 7 Part C, D42, D22): the response headers, a page-by-page check
 * that the Content Security Policy blocks nothing the app needs, and the 3D sandbox's isolation
 * tried from inside its frame.
 */
const INTRO_LESSON = "/learn/intro-to-computer-networks";

/** Collects CSP violations in every document of the page, enforced or report-only. */
async function watchViolations(page: Page) {
  await page.addInitScript(() => {
    const store = window as unknown as { __csp?: string[] };
    store.__csp = [];
    document.addEventListener("securitypolicyviolation", (e) => store.__csp!.push(`${e.violatedDirective} ${e.blockedURI}`));
  });
  return () => page.evaluate(() => (window as unknown as { __csp?: string[] }).__csp ?? []);
}

test.describe("security headers", () => {
  test("pages carry a nonce CSP and the protective headers", async ({ page }) => {
    const response = await page.goto("/");
    const headers = response!.headers();
    const csp = headers["content-security-policy"];
    expect(csp).toMatch(/script-src 'self' 'nonce-[A-Za-z0-9+/=]+' 'strict-dynamic'/);
    expect(csp).toContain("frame-ancestors 'none'");
    expect(csp).toContain("object-src 'none'");
    expect(csp).not.toMatch(/script-src[^;]*'unsafe-inline'/);
    expect(headers["x-frame-options"]).toBe("DENY");
    expect(headers["x-content-type-options"]).toBe("nosniff");
    expect(headers["referrer-policy"]).toBe("strict-origin-when-cross-origin");

    // A fresh nonce for every response.
    const again = (await page.goto("/"))!.headers()["content-security-policy"];
    expect(again).not.toBe(csp);
  });

  test("the reset page never sends its token as a referrer", async ({ page }) => {
    const response = await page.goto("/reset-password?token=secret-token&email=someone%40example.test");
    expect(response!.headers()["referrer-policy"]).toBe("no-referrer");
  });
});

test.describe("content security policy", () => {
  test("nothing the app needs is blocked, in both themes and the admin editor", async ({ page }) => {
    const violations = await watchViolations(page);
    for (const path of ["/", "/courses", "/careers/soc-analyst"]) {
      await page.goto(path);
      await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
      expect(await violations(), path).toEqual([]);
    }

    await login(page, account("security"));
    for (const theme of ["dark", "light"]) {
      await page.evaluate((t) => localStorage.setItem("theme", t), theme);
      for (const path of ["/dashboard", "/roadmap", INTRO_LESSON]) {
        await page.goto(path);
        await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
        expect(await violations(), `${path} (${theme})`).toEqual([]);
      }
    }
  });

  test("the admin lesson editor and its live preview run under the policy", async ({ page }) => {
    const violations = await watchViolations(page);
    await login(page, account("admin"));
    await page.goto("/admin/lessons?q=Introduction");
    await page.getByRole("main").getByRole("link", { name: /Introduction to computer networks/i }).first().click();
    await expect(page).toHaveURL(/\/admin\/lessons\/\d+$/);
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
    await page.getByRole("tab", { name: "Preview" }).click();
    // The 3D frame mounts only near the viewport (D22), so bring its block into view first.
    await page.getByRole("heading", { name: "Follow a packet" }).scrollIntoViewIfNeeded();
    await expect(page.locator('iframe[src="/sandbox/scene3d/frame.html"]').first()).toBeAttached();
    await page.waitForTimeout(1_500);
    expect(await violations()).toEqual([]);
  });
});

test.describe("3D sandbox", () => {
  // Refused requests from inside the sandbox are logged by the browser; refusing them is the point.
  test.use({ allowedConsole: /Refused to connect|Content Security Policy|WebSocket|sandbox|SecurityError/i });

  test("scene code has no network, storage, cookies or access to the page", async ({ page }) => {
    await login(page, account("security"));
    await page.goto(INTRO_LESSON);
    // The 3D view loads only when its section comes near the viewport.
    await page.getByRole("heading", { name: "Follow a packet" }).scrollIntoViewIfNeeded();
    const iframe = page.locator('iframe[src="/sandbox/scene3d/frame.html"]').first();
    await expect(iframe).toBeAttached();
    await expect(iframe).toHaveAttribute("sandbox", "allow-scripts");

    const response = await page.request.get("/sandbox/scene3d/frame.html");
    const csp = response.headers()["content-security-policy"];
    expect(csp).toContain("connect-src 'none'");
    expect(csp).toContain("sandbox allow-scripts");

    await expect.poll(() => page.frames().some((f) => f.url().includes("/sandbox/scene3d/frame.html"))).toBe(true);
    const frame = page.frames().find((f) => f.url().includes("/sandbox/scene3d/frame.html"))!;
    const probe = await frame.evaluate(async () => {
      const attempt = async (run: () => unknown) => {
        try {
          await run();
          return "allowed";
        } catch {
          return "blocked";
        }
      };
      return {
        origin: self.origin,
        fetch: await attempt(() => fetch("http://localhost:8000/api/v1/me", { credentials: "include" })),
        // A blocked WebSocket does not throw; the browser reports the CSP violation instead.
        webSocket: await new Promise<string>((resolve) => {
          document.addEventListener("securitypolicyviolation", (e) => {
            if (e.violatedDirective.startsWith("connect-src") && e.blockedURI.startsWith("ws")) resolve("blocked");
          });
          try {
            new WebSocket("ws://localhost:8000/");
          } catch {
            resolve("blocked");
          }
          setTimeout(() => resolve("allowed"), 3_000);
        }),
        storage: await attempt(() => localStorage.length),
        cookies: await attempt(() => document.cookie),
        parent: await attempt(() => parent.document.title),
      };
    });

    expect(probe).toEqual({
      origin: "null",
      fetch: "blocked",
      webSocket: "blocked",
      storage: "blocked",
      cookies: "blocked",
      parent: "blocked",
    });
  });
});
