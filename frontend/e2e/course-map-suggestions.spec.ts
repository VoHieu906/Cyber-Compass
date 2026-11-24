import { account } from "./support/accounts";
import { expect, signIn, test } from "./support/test";

/**
 * Course map suggestions (F4 Part C, D63) on Python for Security Automation, which has no seeded
 * map, so no other spec sees this one change. An admin starts the map from the course structure,
 * places every lesson with one click, then adds and publishes a lesson and accepts the relation
 * and place suggested for it. Every step is a normal save. The account purge removes the map
 * (saved by an e2e account) and the e2e-* lesson afterwards.
 */
const COURSE = "Python for Security Automation";

test("an admin adds a lesson, accepts its suggested relation and place, and learners see it connected", async ({
  page,
}) => {
  // The course page caches its API call for 60 seconds, so the last step may wait that long.
  test.setTimeout(240_000);
  const run = Date.now();
  const title = `E2E map lesson ${run}`;
  await signIn(page, account("mapAdmin"));

  const openMap = async () => {
    await page.goto("/admin/courses");
    await page.getByLabel("Course", { exact: true }).selectOption({ label: COURSE });
    await expect(page.getByRole("button", { name: "Save the course map" }).or(page.getByRole("button", { name: "Start from the course structure" }))).toBeVisible();
  };
  const saveMap = async () => {
    await page.getByRole("button", { name: "Save the course map" }).click();
    await expect(page.getByText("Course map saved.")).toBeVisible();
  };

  // A course with no map: start one from its structure, lay it out by hand, place every lesson at once.
  await openMap();
  await page.getByRole("button", { name: "Start from the course structure" }).click();
  await page.getByLabel("Layout").selectOption("manual");
  await saveMap();
  await page.getByRole("button", { name: /^Accept all \d+ places$/ }).click();
  await saveMap();
  await expect(page.getByRole("button", { name: /^Accept: Suggested place/ })).toHaveCount(0);

  // A new lesson, published.
  await page.goto("/admin/lessons");
  await page.getByRole("button", { name: "New lesson" }).click();
  const dialog = page.getByRole("dialog");
  await dialog.getByLabel("Course").selectOption({ label: COURSE });
  await dialog.getByLabel("Title").fill(title);
  await dialog.getByLabel("Address").fill(`e2e-map-${run}`);
  await dialog.getByRole("button", { name: "Create and edit" }).click();
  await expect(page.getByRole("heading", { level: 2, name: "Lesson details" })).toBeVisible();
  await page.getByLabel("Summary").fill("Written by the end-to-end suite.");
  await page.getByRole("button", { name: /^Text/ }).first().click();
  await page.getByLabel("Heading").fill("Why automate");
  await page.getByLabel("Text", { exact: true }).fill("Scripts repeat checks exactly.");
  await page.getByRole("button", { name: "Save draft" }).click();
  await expect(page.getByText(/^Draft saved/)).toBeVisible();
  await page.getByRole("button", { name: "Publish" }).click();
  await page.getByRole("dialog").getByLabel(/Note for the history/).fill("First version");
  await page.getByRole("dialog").getByRole("button", { name: "Publish" }).click();
  await expect(page.getByText("Published. Learners now see this version.")).toBeVisible();

  // The map lists it as unconnected and unplaced, each with its fix: accept both and save.
  await openMap();
  const relation = page.getByRole("button", { name: new RegExp(`^Accept: Suggested: ${title} builds on `) });
  await expect(relation).toBeVisible();
  await relation.click();
  await page.getByRole("button", { name: /^Accept: Suggested place/ }).click();
  await expect(page.getByText("Accepted. Save the map to keep it.")).toHaveCount(2);
  await saveMap();
  await expect(page.getByRole("button", { name: /^Accept: Suggested/ })).toHaveCount(0);

  // Learners see it on the map, related to the lesson before it.
  const map = page.getByRole("group", { name: `Course map of ${COURSE}` });
  await expect
    .poll(
      async () => {
        await page.goto("/courses/python-for-security");
        return map.getByRole("button", { name: title }).count();
      },
      { timeout: 120_000, intervals: [5_000] },
    )
    .toBe(1);
  await page.getByText("Text version").click();
  const item = page.locator("section[aria-labelledby=course-map-title] details li").filter({ has: page.getByRole("link", { name: title }) });
  await expect(item.getByText(/^builds on /)).toBeVisible();
});
