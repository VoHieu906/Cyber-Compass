import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

test.describe("sign in and out", () => {
  test("a guest is sent to log in and comes back to the page they asked for", async ({ page }) => {
    await page.goto("/roadmap");
    await expect(page).toHaveURL(/\/login\?next=%2Froadmap$/);

    await login(page, account("auth"), "/roadmap");
    await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
  });

  test.describe("refusals", () => {
    // The API answers a wrong password with 422, which the browser logs.
    test.use({ allowedConsole: [/status of 422/] });

    test("a wrong password is refused without saying which part was wrong", async ({ page }) => {
      const { email } = account("auth");
      await page.goto("/login");
      await page.getByLabel("Email").fill(email);
      await page.getByLabel("Password", { exact: true }).fill("not-the-password-1");
      await page.getByRole("button", { name: "Log in", exact: true }).click();

      await expect(page.getByRole("alert").filter({ hasText: /credentials/i })).toBeVisible();
      await expect(page).toHaveURL(/\/login/);
    });
  });

  test("login lands on the dashboard and logout ends the session", async ({ page }) => {
    const learner = account("auth");
    await login(page, learner);
    await expect(page.getByRole("heading", { name: /^Welcome back/ })).toBeVisible();
    await expect(page.locator('header a[href="/dashboard"]').first()).toHaveAttribute("aria-current", "page");

    await page.getByRole("button", { name: /^Account: / }).click();
    await page
      .getByRole("menuitem", { name: "Log out" })
      .or(page.getByRole("button", { name: "Log out" }))
      .click();
    await expect(page.getByRole("link", { name: "Log in" }).first()).toBeVisible();

    await page.goto("/dashboard");
    await expect(page).toHaveURL(/\/login\?next=%2Fdashboard$/);
  });

  test("an open redirect through next is ignored", async ({ page }) => {
    await login(page, account("auth"), undefined);
    await page.goto("/login?next=https%3A%2F%2Fevil.example%2F");
    // Signed in already: the guest-only page forwards to a safe in-app path instead of the other site.
    await page.waitForURL((url) => url.origin === new URL(page.url()).origin && !url.pathname.startsWith("/login"));
    expect(new URL(page.url()).host).toBe("localhost:3000");
  });
});
