import { account } from "./support/accounts";
import { expect, login, test } from "./support/test";

/**
 * The course map (F3) on the test map that global setup installs on Applied Cryptography
 * (`cybercompass:e2e-course-map`). A guest works it by keyboard alone; a learner follows a part
 * to the exact section of the lesson that teaches it.
 */
const COURSE = "/courses/applied-cryptography";

test.describe("course map", () => {
  test("a guest focuses a lesson by keyboard, sees its parts and the dimmed rest, and returns with Escape", async ({
    page,
  }) => {
    await page.goto(COURSE);
    const map = page.getByRole("group", { name: /Course map of Applied/ });
    await expect(map).toBeVisible();
    await expect(page.getByRole("heading", { name: "Course map", level: 2 })).toBeVisible();

    // One tab stop: the first lesson; the arrow keys follow the relations.
    const hashing = map.getByRole("button", { name: "Hashing and password storage" });
    await hashing.focus();
    await page.keyboard.press("ArrowRight");
    await expect(map.getByRole("button", { name: /Symmetric and asymmetric/ })).toBeFocused();
    await page.keyboard.press("ArrowLeft");
    await expect(hashing).toBeFocused();

    await page.keyboard.press("Enter");
    const panel = page.getByRole("region", { name: /Hashing and password storage: how it is built/ });
    await expect(panel).toBeVisible();
    await expect(hashing).toHaveAttribute("aria-expanded", "true");
    await expect(panel.getByRole("button", { name: "Unique salt" })).toBeVisible();
    await expect(panel.getByRole("button", { name: /Slow hash/ })).toBeVisible();
    // The lesson it contrasts with stays as it is; the unrelated one is dimmed, not removed.
    await expect(map.getByRole("button", { name: /Symmetric and asymmetric/ })).toHaveAttribute(
      "data-state",
      "related",
    );
    const tls = map.getByRole("button", { name: /How TLS protects/ });
    await expect(tls).toHaveAttribute("data-state", "dimmed");
    await expect(tls).toBeVisible();

    await panel.getByRole("button", { name: "Unique salt" }).click();
    const read = panel.getByRole("link", { name: "Read this in the lesson" });
    await expect(read).toHaveAttribute("href", /^\/learn\/hashing-and-passwords#[0-9a-f-]{36}$/);

    await page.keyboard.press("Escape");
    await expect(panel).toBeHidden();
    await expect(hashing).toBeFocused();
    await expect(hashing).toHaveAttribute("aria-expanded", "false");
  });

  test("a learner follows a part to the section that teaches it", async ({ page }) => {
    await login(page, account("courseMap"));
    await page.goto(COURSE);
    const map = page.getByRole("group", { name: /Course map of Applied/ });
    await map.getByRole("button", { name: "Hashing and password storage" }).click();
    const panel = page.getByRole("region", { name: /Hashing and password storage: how it is built/ });
    await panel.getByRole("button", { name: /Slow hash/ }).click();
    const href = await panel.getByRole("link", { name: "Read this in the lesson" }).getAttribute("href");
    await panel.getByRole("link", { name: "Read this in the lesson" }).click();

    await expect(page).toHaveURL(new RegExp(`${href!.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}$`));
    const section = page.locator(`section[id="${href!.split("#")[1]}"]`);
    await expect(section).toBeInViewport();
    await expect(section.getByRole("heading", { name: "Passwords need slow hashes" })).toBeVisible();
  });

  test("below md the outline is the map", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(COURSE);
    await expect(page.getByRole("group", { name: /Course map of Applied/ })).toBeHidden();
    const outline = page.locator("section[aria-labelledby=course-map-title]");
    await expect(outline.getByRole("link", { name: "Hashing and password storage" }).first()).toBeVisible();
    await expect(outline.getByText(/Built as: Pipeline/).first()).toBeVisible();
  });
});
