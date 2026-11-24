import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { expect, signIn, test } from "./support/test";

/** The compass menu and keyboard shortcuts (Phase 7.5, D51 to D53), on its own account. */
test.describe.configure({ mode: "serial" });

const focusedLabel = (page: Page) => page.evaluate(() => document.activeElement?.getAttribute("aria-label") ?? "");

/** The trigger renders once /me has answered, so the page is hydrated when it shows. */
async function trigger(page: Page) {
  const button = page.getByRole("button", { name: "Compass menu" });
  await expect(button).toBeVisible();
  return button;
}

/** Presses `key` until the focused item's name starts with `name` (type-ahead cycles). */
async function typeTo(page: Page, key: string, name: string) {
  for (let i = 0; i < 6 && !(await focusedLabel(page)).startsWith(name); i++) await page.keyboard.press(key);
  expect(await focusedLabel(page)).toMatch(new RegExp(`^${name}`));
}

test.describe("compass", () => {
  // The dark pass signs in as a second account: one account's two passes would pass the API's 120 a minute.
  test.beforeEach(async ({ page }, info) => {
    await signIn(page, account(info.title.includes("dark") ? "compass2" : "compass"));
  });

  test("reaches a settings tab with the keyboard only and marks where the learner is", async ({ page }) => {
    const button = await trigger(page);
    await page.keyboard.press("Alt+Shift+KeyC");
    await expect(page.getByRole("menu", { name: "Compass" })).toBeVisible();
    await expect(button).toHaveAttribute("aria-expanded", "true");
    // Starts on the current page.
    await expect.poll(() => focusedLabel(page)).toMatch(/^Dashboard, 1 of \d+/);
    await expect(page.getByRole("menuitem", { name: /^Dashboard/ })).toHaveAttribute("aria-current", "page");

    await typeTo(page, "s", "Settings");
    await page.keyboard.press("Enter");
    await expect(page.getByRole("menu", { name: /^Settings/ })).toBeVisible();
    await typeTo(page, "p", "Preferences");
    await page.keyboard.press("Enter");
    await expect(page).toHaveURL(/\/settings\/preferences$/);
    await expect(page.getByRole("menu")).toHaveCount(0);
    await expect(button).toBeFocused();

    // Open again: the active section and tab are marked, and look different from the others.
    await page.keyboard.press("Alt+Shift+KeyC");
    await expect.poll(() => focusedLabel(page)).toMatch(/^Settings/);
    await page.keyboard.press("ArrowDown");
    const current = page.getByRole("menuitem", { name: /^Preferences/ });
    await expect(current).toHaveAttribute("aria-current", "page");
    const fill = (name: RegExp) => page.getByRole("menuitem", { name }).locator("path.compass-seg").evaluate((p) => getComputedStyle(p).fill);
    expect(await fill(/^Preferences/)).not.toBe(await fill(/^Security/));
    await page.keyboard.press("Escape");
    await expect(page.getByRole("menu", { name: /^Settings/ })).toHaveCount(0);
    await page.keyboard.press("Escape");
    await expect(page.getByRole("menu")).toHaveCount(0);
    await expect(button).toBeFocused();
  });

  test("a rebound shortcut opens it, and switching it off removes it", async ({ page }) => {
    await page.goto("/settings/shortcuts");
    await page.getByRole("button", { name: "Change the shortcut for Open or close the compass" }).click();
    await page.keyboard.press("Alt+Shift+KeyJ");
    await expect(page.getByText("Shortcuts saved.")).toBeVisible();

    await page.locator("body").click({ position: { x: 5, y: 200 } });
    await page.keyboard.press("Alt+Shift+KeyC");
    await expect(page.getByRole("menu")).toHaveCount(0);
    await page.keyboard.press("Alt+Shift+KeyJ");
    await expect(page.getByRole("menu", { name: "Compass" })).toBeVisible();
    await page.keyboard.press("Escape");
    await page.getByRole("button", { name: "Reset all shortcuts" }).click();
    await expect(page.getByText("Shortcuts saved.")).toBeVisible();

    await page.goto("/settings/preferences");
    await trigger(page);
    const toggle = page.getByRole("switch", { name: "Show the compass menu" });
    await toggle.uncheck();
    await expect(page.getByRole("button", { name: "Compass menu" })).toHaveCount(0);
    await page.keyboard.press("Alt+Shift+KeyC");
    await expect(page.getByRole("menu")).toHaveCount(0);
    // Stays off on the next page load (saved on the account).
    await page.reload();
    await expect(toggle).not.toBeChecked();
    await expect(page.getByRole("button", { name: "Compass menu" })).toHaveCount(0);
    await toggle.check();
    await trigger(page);
  });

  for (const theme of ["light", "dark"] as const) {
    test(`fits every screen in the ${theme} theme`, async ({ page }) => {
      await page.emulateMedia({ colorScheme: theme });
      for (const [width, height] of [
        [390, 844],
        [1280, 800],
        [1920, 1080],
        [3840, 1080],
      ]) {
        await page.setViewportSize({ width, height });
        await page.goto("/learn/osi-model");
        const button = await trigger(page);
        const box = (await button.boundingBox())!;
        expect(box.x + box.width, `${width}: trigger inside the screen`).toBeLessThanOrEqual(width);
        if (width >= 1920) {
          // Beside the content, not in the far corner of a wide screen.
          const contentRight = await page.locator("main .container-page").first().evaluate((el) => el.getBoundingClientRect().right);
          expect(box.x, `${width}: trigger in the content gutter`).toBeGreaterThanOrEqual(contentRight - 64);
          expect(box.x + box.width, `${width}: trigger close to the content`).toBeLessThanOrEqual(contentRight + 8);
        }

        await button.click();
        const onPage = page.getByRole("menuitem", { name: /^On this page/ });
        await onPage.hover();
        await expect(page.getByRole("menu", { name: /^On this page/ })).toBeVisible();
        const ring = (await page.locator("svg[viewBox]").filter({ has: page.getByRole("menu") }).boundingBox())!;
        expect(ring.x, `${width}: left edge`).toBeGreaterThanOrEqual(0);
        expect(ring.y, `${width}: below the header`).toBeGreaterThanOrEqual(64);
        expect(ring.x + ring.width, `${width}: right edge`).toBeLessThanOrEqual(width);
        expect(ring.y + ring.height, `${width}: bottom edge`).toBeLessThanOrEqual(height);
        await page.keyboard.press("Escape");
        await page.keyboard.press("Escape");
        await expect(page.getByRole("menu")).toHaveCount(0);
      }
    });
  }
});
