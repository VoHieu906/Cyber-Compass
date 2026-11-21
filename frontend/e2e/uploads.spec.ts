import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

/**
 * An upload end to end (Phase 7 Part F): the avatar goes through the API's re-encoding to the
 * uploads disk (the local `public` disk in development, S3 on AWS and MinIO in the local
 * production stack) and comes back as an image the browser can load under the CSP. The photo is
 * removed at the end, so the run leaves no file behind.
 */
test("an avatar uploads, displays from the uploads origin and is removed", async ({ page }) => {
  await login(page, account("uploads"));
  await page.goto("/settings/profile");
  await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();

  // A real PNG, 80 x 80 pixels (the API refuses images under 64 pixels).
  const png = await page.screenshot({ clip: { x: 0, y: 0, width: 80, height: 80 } });
  await page.locator('input[type="file"]').setInputFiles({ name: "avatar.png", mimeType: "image/png", buffer: png });
  await expect(page.getByText("Photo updated.")).toBeVisible();

  const avatar = page.getByRole("button", { name: /^Account: / }).locator("img");
  await expect(avatar).toHaveAttribute("src", /\/avatars\/\d+\/[A-Za-z0-9]{32}\.webp$/);
  await expect.poll(() => avatar.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth)).toBe(256);

  await page.getByRole("button", { name: "Remove" }).click();
  await expect(page.getByText("Photo removed.")).toBeVisible();
  await expect(avatar).toHaveCount(0);
});
