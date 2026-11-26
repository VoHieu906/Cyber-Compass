import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { audit } from "./support/audit";
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

  test("an admin creates, proves, previews and publishes a lab, and a breaking change restarts learners after confirmation", async ({
    page,
    browser,
  }) => {
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
    await audit(page, "editor-details");

    // Scenario: the shell lab has a form. A bad value is reported beside its field by the schema mirror.
    await openTab(page, "Scenario");
    const host = page.getByLabel("Host name");
    await host.fill("Lab 01");
    await expect(page.getByText(/Use lowercase letters, digits and hyphens, starting with a letter/)).toBeVisible();
    await audit(page, "editor-scenario-problems");
    await host.fill("lab-01");
    await expect(page.getByText(/Use lowercase letters, digits and hyphens, starting with a letter/)).toHaveCount(0);
    // Adding a file through the form shows up in the JSON of the same scenario, and nothing is lost on the way back.
    await page.getByRole("button", { name: "Add a file" }).click();
    await page.getByLabel("Content").fill("a note from the end-to-end suite");
    await audit(page, "editor-scenario");
    await page.getByRole("radio", { name: "JSON" }).click();
    await expect(page.getByLabel(/Scenario \(JSON/)).toHaveValue(/a note from the end-to-end suite/);
    await audit(page, "editor-scenario-json");
    await page.getByRole("radio", { name: "Form" }).click();
    await expect(page.getByLabel("Host name")).toHaveValue("lab-01");
    await openTab(page, "Checkpoints");
    await expect(page.getByRole("heading", { name: "Checkpoint 1" })).toBeVisible();
    await audit(page, "editor-checkpoints");

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
    await audit(page, "editor-solutions-failing");
    await actions.fill(good);
    await page.getByRole("button", { name: "Run solutions" }).click();
    await expect(page.getByText("Every solution completes the lab and no decoy does.")).toBeVisible();
    await audit(page, "editor-solutions");

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
    await audit(page, "editor-preview");

    // The preview's state is kept per admin and lab: another tab moving it on makes this tab's next
    // step stale. The panel says so in words and offers a restart, never an error code.
    const other = await page.context().newPage();
    await other.goto(page.url());
    await other.getByRole("tab", { name: "Preview" }).click();
    await other.locator("#lab-command").fill("pwd");
    await other.locator("#lab-command").press("Enter");
    await expect(other.getByRole("log").getByText("/home/learner").first()).toBeVisible();
    await other.close();
    await command.fill("pwd");
    await command.press("Enter");
    await expect(page.getByText(/This preview ran out of time, or the draft changed under it/)).toBeVisible();
    await expect(page.getByText(/Reload to continue/)).toHaveCount(0);
    await audit(page, "editor-preview-expired");
    await page.getByRole("button", { name: "Restart preview" }).first().click();
    await expect(page.getByText(/ran out of time/)).toHaveCount(0);

    // Publish: learners can see the lab now.
    await page.getByRole("button", { name: "Publish" }).click();
    await audit(page, "publish-dialog");
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
    await page.getByLabel("Host name").fill("lab-02");
    await page.getByRole("button", { name: "Publish" }).click();
    await page.getByRole("dialog", { name: "Publish this lab?" }).getByRole("button", { name: "Publish" }).click();
    const confirm = page.getByRole("dialog", { name: "This change restarts the lab for learners" });
    await expect(confirm.getByText(/1 learner is part-way through this lab/)).toBeVisible();
    await audit(page, "restart-dialog");
    await confirm.getByRole("button", { name: "Publish and restart" }).click();
    await expect(page.getByText("Published. Learners see this version now.")).toBeVisible();

    // The learner is told once that the lab started over; their earlier step is gone.
    await learner.reload();
    await expect(
      learner.getByText(/This lab was updated since you last worked on it, so it started over/),
    ).toBeVisible();
    await expect(learner.getByRole("log").getByText("cat /etc/hostname")).toHaveCount(0);
    await audit(learner, "learner-restart-notice");
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
    await audit(page, "admin-list");
    await row.getByRole("button", { name: "Archive" }).click();
    await expect(row.getByText("Archived")).toBeVisible();

    // History: both published versions are listed, and one restores into the draft.
    await row.getByRole("link", { name: TITLE }).click();
    await openTab(page, "History");
    await expect(page.getByText("Version 2")).toBeVisible();
    await audit(page, "editor-history");
    await page.getByRole("button", { name: "Restore" }).last().click();
    await page.getByRole("dialog").getByRole("button", { name: "Restore" }).click();
    await expect(page.getByText("Restored into the draft. Publish to make it live.")).toBeVisible();
    await expect(page.getByText("Unpublished changes")).toBeVisible();
  });

  test("a learner sees the conditions that are true at the start as ones to keep", async ({ page }) => {
    await signIn(page, account("labs"));
    await page.goto("/labs/lock-down-a-web-server");
    await page.getByRole("button", { name: "Start the lab" }).click();
    await expect(page.getByText("0 of 3 tasks done")).toBeVisible();
    await expect(page.getByText("2 of 2 conditions still true")).toBeVisible();
    await expect(page.getByText("Already true. Keep it that way.", { exact: true })).toHaveCount(2);
    await audit(page, "learner-already-true");
  });
});
