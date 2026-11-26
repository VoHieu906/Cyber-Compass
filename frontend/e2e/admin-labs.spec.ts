import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { expect, signIn, test } from "./support/test";

/**
 * Lab management (F7 Part B, D73 to D79): an admin creates a lab, edits it, proves its solutions,
 * plays the preview without touching any learner record, publishes, and a later change that restarts
 * learners asks for confirmation first. The learner sees the restart notice once. Own admin account
 * (`labsAdmin`) because the admin rate limit is per user.
 */
const SLUG = `e2e-lab-${Date.now().toString(36)}`;
const TITLE = `E2E lab ${SLUG.slice(-4)}`;

test.describe("lab management", () => {
  // A stale or changed preview and an unsaved edit are answered with 409 and 422 on purpose.
  test.use({ allowedConsole: /status of (409|422)/ });

  async function openTab(page: Page, name: string) {
    await page.getByRole("tab", { name }).click();
    await expect(page.getByRole("tab", { name })).toHaveAttribute("aria-selected", "true");
  }

  test("an admin creates, proves, previews and publishes a lab, and a breaking change restarts learners after confirmation", async ({ page, browser }) => {
    await signIn(page, account("labsAdmin"));
    await page.goto("/admin/labs");
    await expect(page.getByRole("heading", { name: "Labs", level: 1 })).toBeVisible();

    // The seeded labs come from a content file until an admin edits them.
    const seeded = page.getByRole("row").filter({ hasText: "Investigate a suspicious login" });
    await expect(seeded.getByText("Content file")).toBeVisible();

    // Create: the slug follows the title; the lab starts as an unpublished draft.
    await page.getByRole("button", { name: "New lab" }).click();
    const create = page.getByRole("dialog", { name: "New lab" });
    await create.getByLabel("Title").fill(TITLE);
    await create.getByLabel("Slug").fill(SLUG);
    await create.getByLabel("Engine").selectOption("linux-shell");
    await create.getByRole("button", { name: "Create lab" }).click();
    await page.waitForURL(/\/admin\/labs\/\d+$/);
    await expect(page.getByRole("heading", { name: TITLE, level: 1 })).toBeVisible();
    await expect(page.getByText("Owned by the database")).toBeVisible();

    // Details: edit the summary; the page says there are unsaved changes, then saves.
    await page.getByLabel("Summary").fill("A lab made by the end-to-end suite.");
    await expect(page.getByText("Unsaved changes")).toBeVisible();
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(page.getByText("Unsaved changes")).toBeHidden();

    // A broken scenario is reported by path and the editor jumps to where it is.
    await openTab(page, "Scenario");
    const json = page.getByLabel("Scenario (JSON, without the checkpoints)");
    const original = await json.inputValue();
    await json.fill(original.replace('"hostname": "lab-01"', '"hostname": "Lab 01"'));
    await expect(page.getByText("scenario.hostname").first()).toBeVisible();
    await json.fill(original);
    await expect(page.getByText("scenario.hostname")).toHaveCount(0);

    // Solutions: the starter's solution completes the lab and its decoy does not.
    await openTab(page, "Solutions");
    await page.getByRole("button", { name: "Run solutions" }).click();
    await expect(page.getByText("Every solution completes the lab and no decoy does.")).toBeVisible();
    // Break the solution: the check says so, and publishing would be refused.
    const actions = page.getByLabel("Actions (JSON list)").first();
    const good = await actions.inputValue();
    await actions.fill("[]");
    await page.getByRole("button", { name: "Run solutions" }).click();
    await expect(page.getByText("Something does not work. See the entries below.")).toBeVisible();
    await actions.fill(good);
    await page.getByRole("button", { name: "Run solutions" }).click();
    await expect(page.getByText("Every solution completes the lab and no decoy does.")).toBeVisible();

    // Preview plays the saved draft with no learner record: solve it as a learner would.
    await openTab(page, "Preview");
    const command = page.locator("#lab-command");
    await command.fill("cat /etc/hostname");
    await command.press("Enter");
    await expect(page.getByRole("log").getByText("lab-01")).toBeVisible();
    const task = page.getByRole("complementary").locator("li").filter({ hasText: "What is the host name?" });
    await task.getByRole("textbox").fill("lab-01");
    await task.getByRole("button", { name: "Check" }).click();
    await expect(page.getByText("Lab complete").first()).toBeVisible();

    // Publish: learners can see the lab now.
    await page.getByRole("button", { name: "Publish" }).click();
    await page.getByRole("dialog", { name: "Publish this lab?" }).getByRole("button", { name: "Publish" }).click();
    await expect(page.getByText("Published. Learners see this version now.")).toBeVisible();

    // A learner starts it and takes one step.
    const learnerContext = await browser.newContext();
    const learner = await learnerContext.newPage();
    const errors: string[] = [];
    learner.on("pageerror", (e) => errors.push(e.message));
    await signIn(learner, account("labs"));
    await learner.goto(`/labs/${SLUG}`);
    await learner.getByRole("button", { name: "Start the lab" }).click();
    await learner.locator("#lab-command").fill("cat /etc/hostname");
    await learner.locator("#lab-command").press("Enter");
    await expect(learner.getByRole("log").getByText("lab-01")).toBeVisible();

    // A change that rebuilds the scenario restarts that learner: the admin must confirm, and sees the count.
    await openTab(page, "Scenario");
    const scenario = page.getByLabel("Scenario (JSON, without the checkpoints)");
    await scenario.fill((await scenario.inputValue()).replace('"hostname": "lab-01"', '"hostname": "lab-02"'));
    await page.getByRole("button", { name: "Publish" }).click();
    await page.getByRole("dialog", { name: "Publish this lab?" }).getByRole("button", { name: "Publish" }).click();
    const confirm = page.getByRole("dialog", { name: "This change restarts the lab for learners" });
    await expect(confirm.getByText(/1 learner is part-way through this lab/)).toBeVisible();
    await confirm.getByRole("button", { name: "Publish and restart" }).click();
    await expect(page.getByText("Published. Learners see this version now.")).toBeVisible();

    // The learner is told once that the lab started over; their earlier step is gone.
    await learner.reload();
    await expect(learner.getByText(/This lab was updated since you last worked on it, so it started over/)).toBeVisible();
    await expect(learner.getByRole("log").getByText("cat /etc/hostname")).toHaveCount(0);
    // Reading it again (a reload) does not use it up; dismissing it does, for good.
    await learner.reload();
    await expect(learner.getByText(/so it started over/)).toBeVisible();
    await learner.getByRole("button", { name: "Dismiss" }).click();
    await expect(learner.getByText(/so it started over/)).toHaveCount(0);
    await learner.reload();
    await expect(learner.locator("#lab-command")).toBeVisible();
    await expect(learner.getByText(/so it started over/)).toHaveCount(0);
    expect(errors).toEqual([]);
    await learnerContext.close();

    // The list shows it as database-owned and published, and archiving hides it from learners.
    await page.goto("/admin/labs");
    const row = page.getByRole("row").filter({ hasText: TITLE });
    await expect(row.getByText("Database")).toBeVisible();
    await row.getByRole("button", { name: "Archive" }).click();
    await expect(row.getByText("Archived")).toBeVisible();

    // History: both published versions are listed, and one restores into the draft.
    await row.getByRole("link", { name: TITLE }).click();
    await openTab(page, "History");
    await expect(page.getByText("Version 2")).toBeVisible();
    await page.getByRole("button", { name: "Restore" }).last().click();
    await page.getByRole("dialog").getByRole("button", { name: "Restore" }).click();
    await expect(page.getByText("Restored into the draft. Publish to make it live.")).toBeVisible();
    await expect(page.getByText("Unpublished changes")).toBeVisible();
  });
});
