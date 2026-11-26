import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { audit } from "./support/audit";
import { expect, signIn, test } from "./support/test";

/**
 * The incident-response lab (F7 Part E, D80): technical decisions with visible effects, the
 * attacker's next move, a dead end said plainly (and cleared by starting over), a second order that
 * also completes, a verdict that cites the state, and the debrief of the path taken.
 */
const LAB = "/labs/contain-a-ransomware-incident";

const act = (page: Page, name: string | RegExp) => page.getByRole("button", { name }).first();

async function choose(page: Page, name: string | RegExp) {
  const decisions = page.getByRole("region", { name: "Timeline" }).getByRole("listitem");
  const before = await decisions.count();
  await act(page, name).click();
  await expect.poll(() => decisions.count()).toBeGreaterThan(before);
}

test.describe("incident lab", () => {
  test("a dead end, starting over, then another order to completion and the debrief", async ({ page }) => {
    await signIn(page, account("incident"));
    await page.goto(LAB);
    await page.getByRole("button", { name: "Start the lab" }).click();

    const state = page.getByRole("region", { name: "Incident state" });
    await expect(state.getByText("Encrypting process")).toBeVisible();
    await expect(page.getByRole("region", { name: "Debrief" })).toHaveCount(0);
    await expect(page.getByText("Preserves volatile evidence")).toHaveCount(0);
    // Not open yet: a restore needs the incident contained first.
    await expect(act(page, /Rebuild fs-01/)).toBeDisabled();
    await audit(page, "incident-start");

    // A decision shows what it changed and the attacker's next move.
    await choose(page, /Disable fs-01's switch port/);
    await expect(state.getByText("Isolated")).toBeVisible();
    const timeline = page.getByRole("region", { name: "Timeline" });
    await expect(timeline.getByText(/uses svc-backup over SMB to reach fs-02/)).toBeVisible();
    await expect(act(page, /Disable fs-01's switch port/)).toBeDisabled();

    // Powering off destroys the evidence: the lab says a task is out of reach, and not which choice would have saved it.
    await choose(page, /Power fs-01 off at once/);
    const alert = page.getByRole("alert").filter({ hasText: "can no longer complete every task" });
    await expect(alert).toBeVisible();
    await expect(alert).toContainText("A memory image and the connection table are preserved.");
    await expect(alert).toContainText("Start over to try a different approach.");
    await expect(alert).not.toContainText(/capture|isolate|power/i);
    await audit(page, "incident-dead-end");

    // Start over, and the dead end and the timeline are gone.
    await page.getByRole("button", { name: "Start over" }).click();
    await page.getByRole("dialog").getByRole("button", { name: "Start over" }).click();
    await expect(page.getByRole("alert").filter({ hasText: "can no longer complete every task" })).toHaveCount(0);
    await expect(page.getByText(/No decisions yet/)).toBeVisible();

    // Another order: isolate and stop first, capture after, rotate before revoking, then restore.
    for (const name of [
      /Disable fs-01's switch port/,
      /Stop the encrypting process/,
      /Capture a memory image/,
      /Reset svc-backup's password/,
      /Revoke svc-backup's Kerberos tickets/,
    ])
      await choose(page, name);
    await expect(page.getByRole("alert").filter({ hasText: "can no longer complete every task" })).toHaveCount(0);
    await expect(act(page, /Rebuild fs-01/)).toBeEnabled();
    await audit(page, "incident-ready");
    await choose(page, /Rebuild fs-01/);
    await expect(page.getByRole("heading", { name: "The incident has ended" })).toBeVisible();
    await expect(page.getByRole("list", { name: "Actions you can take" })).toHaveCount(0);

    // The verdict, citing the state it rests on.
    const verdict = page.getByRole("complementary").locator("li").filter({ hasText: "Give your verdict" });
    await verdict.getByRole("radio", { name: /Service-account hygiene/ }).check();
    await verdict.getByLabel(/svc-backup credentials: Rotated/).check();
    await verdict.getByLabel(/fs-01 network: Isolated/).check();
    await audit(page, "incident-concluding");
    await verdict.getByRole("button", { name: "Check my conclusion" }).click();
    await expect(verdict.getByText(/^Task 6, done:/)).toBeVisible();

    // Complete: the debrief shows the path, each trade-off and where another choice would have led.
    const debrief = page.getByRole("region", { name: "Debrief" });
    await expect(debrief).toBeVisible();
    await expect(debrief.getByRole("heading", { name: "Debrief" })).toBeFocused();
    await expect(debrief.getByText("Your path")).toBeVisible();
    await expect(debrief.getByText(/Stops the transfer and the spread to other shares/)).toBeVisible();
    await expect(debrief.getByText("a task becomes impossible").first()).toBeVisible();
    await expect(debrief.getByText("every task stays possible").first()).toBeVisible();
    await expect(debrief.getByText("Reached in 1 try.")).toBeVisible();
    await audit(page, "incident-debrief");

    // A reload keeps it.
    await page.reload();
    await expect(page.getByRole("region", { name: "Debrief" })).toBeVisible();
  });
});
