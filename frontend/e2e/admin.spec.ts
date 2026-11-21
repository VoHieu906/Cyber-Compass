import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

/**
 * Admin console smoke test (D16), read-only so the run leaves no audit rows or content behind.
 * The API is the gate; the learner case checks that the console says so instead of breaking.
 */
test.describe("admin console", () => {
  test("an admin sees the overview, finds a user and lists lessons", async ({ page }) => {
    await login(page, account("admin"));
    await page.goto("/admin");
    await expect(page.getByRole("heading", { name: "Overview", level: 1 })).toBeVisible();
    await expect(page.getByText("Accounts", { exact: true })).toBeVisible();

    const learner = account("search");
    await page.goto("/admin/users");
    await page.getByRole("main").getByRole("searchbox").fill(learner.email);
    await expect(page.getByRole("main").getByText(learner.email)).toBeVisible();

    await page.goto("/admin/lessons");
    await expect(
      page
        .getByRole("main")
        .getByRole("link", { name: /OSI model/i })
        .first(),
    ).toBeVisible();
  });

  test.describe("as a learner", () => {
    // The admin API answers a learner with 403, which the browser logs.
    test.use({ allowedConsole: [/status of 403/] });

    test("the console says it is for admins only", async ({ page }) => {
      await login(page, account("search"));
      await page.goto("/admin");
      await expect(page.getByRole("heading", { name: "Admins only" })).toBeVisible();
      await expect(page.getByText("Accounts", { exact: true })).toBeHidden();
    });
  });
});
