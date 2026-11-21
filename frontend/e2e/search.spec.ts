import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

/** Header search (D36): keyboard shortcuts, grouped results, and no drafts reaching learners. */
test.describe("global search", () => {
  test("a guest opens search with / and follows a result", async ({ page }) => {
    await page.goto("/");
    await page.locator("body").press("/");
    const box = page.getByRole("searchbox");
    await expect(box).toBeFocused();
    await box.fill("net");

    const dialog = page.getByRole("dialog");
    await expect(dialog.getByRole("heading", { name: "Courses" })).toBeVisible();
    await expect(dialog.getByRole("heading", { name: "Lessons" })).toBeVisible();
    for (const mark of await dialog.locator("mark").allInnerTexts()) expect(mark.toLowerCase()).toBe("net");

    await dialog
      .locator("section")
      .filter({ has: page.getByRole("heading", { name: "Courses" }) })
      .getByRole("link")
      .first()
      .click();
    await expect(page).toHaveURL(/\/courses\/[a-z0-9-]+$/);
    await expect(dialog).toBeHidden();
  });

  test("Ctrl+K, nothing found, and Escape returning focus to where it was", async ({ page }) => {
    await login(page, account("search"));
    const before = page.getByRole("banner").getByRole("link", { name: "Courses", exact: true }).first();
    await before.focus();
    await page.keyboard.press("Control+KeyK");
    await page.getByRole("searchbox").fill("zqxw");
    await expect(page.getByText("Nothing matches “zqxw”", { exact: false })).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(page.getByRole("dialog")).toBeHidden();
    await expect(before).toBeFocused();

    // Opened from the button, Escape returns to the button.
    const button = page.getByRole("button", { name: /^Search/ });
    await button.click();
    await expect(page.getByRole("searchbox")).toBeFocused();
    await page.keyboard.press("Escape");
    await expect(button).toBeFocused();
  });

  test("/ typed inside a field stays a slash", async ({ page }) => {
    await page.goto("/courses");
    const field = page.getByRole("main").getByRole("searchbox").first();
    await field.fill("");
    await field.pressSequentially("a/b");
    await expect(field).toHaveValue("a/b");
    await expect(page.getByRole("dialog")).toBeHidden();
  });
});
