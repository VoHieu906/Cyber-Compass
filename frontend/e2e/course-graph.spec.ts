import { account } from "./support/accounts";
import { expect, signIn, test } from "./support/test";

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

    const tlsBefore = (await map.getByRole("button", { name: /How TLS protects/ }).boundingBox())!;
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
    // The node grew in place, inside the map, and the lesson to its right moved right to make room.
    const frame = (await panel.boundingBox())!;
    const card = (await hashing.boundingBox())!;
    expect(Math.abs(frame.x - card.x)).toBeLessThan(2);
    expect(Math.abs(frame.y - card.y)).toBeLessThan(2);
    await expect.poll(async () => (await tls.boundingBox())!.x).toBeGreaterThan(tlsBefore.x + 100);

    await panel.getByRole("button", { name: "Unique salt" }).click();
    const read = panel.getByRole("link", { name: "Read this in the lesson" });
    await expect(read).toHaveAttribute("href", /^\/learn\/hashing-and-passwords#[0-9a-f-]{36}$/);

    await page.keyboard.press("Escape");
    await expect(panel).toBeHidden();
    await expect(hashing).toBeFocused();
    await expect(hashing).toHaveAttribute("aria-expanded", "false");
  });

  test("a lesson's problem shows in a tooltip on hover and on keyboard focus, and Escape closes it", async ({ page }) => {
    await page.goto(COURSE);
    const map = page.getByRole("group", { name: /Course map of Applied/ });
    const hashing = map.getByRole("button", { name: "Hashing and password storage" });
    const tip = page.getByTestId("course-map-tip");

    // The node shows its title only; screen readers still get the problem as its description.
    await expect(hashing).not.toContainText("Store passwords");
    await expect(hashing).toHaveAccessibleDescription(/Store passwords so that a stolen database/);

    // Hovering before hydration does nothing, so hover again until the page answers.
    await expect(async () => {
      await page.mouse.move(0, 0);
      await hashing.hover();
      await expect(tip).toContainText("Store passwords so that a stolen database", { timeout: 1_000 });
    }).toPass();
    await page.mouse.move(0, 0);
    await expect(tip).toBeHidden();

    // Keyboard: the arrow key moves focus, and the tooltip follows it.
    await hashing.focus();
    await page.keyboard.press("ArrowRight");
    const symmetric = map.getByRole("button", { name: /Symmetric and asymmetric/ });
    await expect(symmetric).toBeFocused();
    await expect(tip).toContainText("Share secrets with someone you have never met");
    const box = (await tip.boundingBox())!;
    const viewport = page.viewportSize()!;
    expect(box.x).toBeGreaterThanOrEqual(0);
    expect(box.x + box.width).toBeLessThanOrEqual(viewport.width);
    await page.keyboard.press("Escape");
    await expect(tip).toBeHidden();
    await expect(symmetric).toHaveAttribute("aria-expanded", "false");
  });

  test("a learner follows a part to the section that teaches it", async ({ page }) => {
    await signIn(page, account("courseMap"));
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
