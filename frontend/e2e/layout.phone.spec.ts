import { account } from "./support/accounts";
import { expect, headerOverflow, signIn, pageOverflow, test } from "./support/test";

/**
 * Phone width (390px): the header's primary navigation lives in the Menu button, and the main
 * signed-in pages fit without horizontal scrolling. Part E sweeps every route; this guards the
 * critical ones on every run.
 */
const PAGES = [
  "/dashboard",
  "/roadmap",
  "/courses",
  "/learn/osi-model",
  "/labs",
  "/notifications",
  "/settings/profile",
];

test("signed-in pages fit a phone and the menu holds the navigation", async ({ page }) => {
  await signIn(page, account("phone"));

  for (const path of PAGES) {
    await page.goto(path);
    await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
    expect(await pageOverflow(page), `${path} page overflow`).toBeLessThanOrEqual(0);
    expect(await headerOverflow(page), `${path} header overflow`).toBeLessThanOrEqual(0);
  }

  await page.goto("/dashboard");
  await page.getByRole("button", { name: "Menu" }).click();
  const menu = page.getByRole("navigation", { name: "Main" }).last();
  await menu.getByRole("link", { name: "Roadmap" }).click();
  await expect(page).toHaveURL("/roadmap");
});
