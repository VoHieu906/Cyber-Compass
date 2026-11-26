import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { audit } from "./support/audit";
import { expect, signIn, test } from "./support/test";

/**
 * The evidence-review lab (F7 Part D, D80): mark lines of an email's raw headers, what the recipient
 * saw and a mail log, by mouse and by keyboard; the limit on marks; a verdict that cites the marked
 * evidence; and the debrief that appears only once the lab is complete.
 */
const LAB = "/labs/spot-the-phishing-email";

const lines = (page: Page, name: RegExp) => page.getByRole("listbox", { name }).getByRole("option");

async function mark(page: Page, name: RegExp, line: number) {
  const option = lines(page, name).nth(line - 1);
  await option.click();
  await expect(option).toHaveAttribute("aria-selected", "true");
}

test.describe("evidence lab", () => {
  test("marks evidence, concludes and reads the debrief", async ({ page }) => {
    await signIn(page, account("evidence"));
    await page.goto(LAB);
    await page.getByRole("button", { name: "Start the lab" }).click();
    await expect(page.getByRole("listbox", { name: /Raw message, lines of The message/ })).toBeVisible();

    // Nothing of the reasoning is on the page yet.
    await expect(page.getByRole("region", { name: "Debrief" })).toHaveCount(0);
    await expect(page.getByText("DMARC fails on alignment")).toHaveCount(0);
    await audit(page, "evidence-start");

    // Marking everything is over the limit, and the page says so.
    const raw = /Raw message, lines of The message/;
    for (let n = 1; n <= 13; n++) await mark(page, raw, n);
    await expect(page.getByText(/Too many lines marked here: at most 12/)).toBeVisible();
    await audit(page, "evidence-over-limit");
    for (const n of [13, 12, 11, 10, 9, 8, 7, 5, 3, 2, 1]) {
      const option = lines(page, raw).nth(n - 1);
      await option.click();
      await expect(option).toHaveAttribute("aria-selected", "false");
    }
    await expect(page.getByText("2 of at most 12 lines marked").first()).toBeVisible();

    // The keyboard: arrows move line by line, Space and Enter toggle.
    const fourth = lines(page, raw).nth(3);
    await expect(fourth).toHaveAttribute("aria-selected", "true");
    await lines(page, raw).nth(0).focus();
    await page.keyboard.press("ArrowDown");
    await expect(lines(page, raw).nth(1)).toBeFocused();
    await page.keyboard.press("Space");
    await expect(lines(page, raw).nth(1)).toHaveAttribute("aria-selected", "true");
    await page.keyboard.press("Enter");
    await expect(lines(page, raw).nth(1)).toHaveAttribute("aria-selected", "false");

    await mark(page, raw, 15);
    // The other view of the same message is a segmented control; its lines are marked on their own.
    await page.getByRole("radio", { name: "What the mail client displays" }).click();
    await mark(page, /What the mail client displays, lines of The message/, 5);
    // Marks of the other view are kept.
    await page.getByRole("radio", { name: "Raw message" }).click();
    await expect(lines(page, raw).nth(3)).toHaveAttribute("aria-selected", "true");

    const tasks = page.getByRole("complementary");
    await expect(tasks.getByText(/^Task 1, done:/)).toBeVisible();
    const question = tasks.locator("li").filter({ hasText: "Which domain does the link" });
    await question.getByRole("textbox").fill("larkfield-secure.example.net");
    await question.getByRole("button", { name: "Check" }).click();
    await expect(question.getByText(/^Task 2, done:/)).toBeVisible();

    // A verdict: wrong first (nothing cited), then the right one with the marked evidence.
    const verdict = tasks.locator("li").filter({ hasText: "Give your verdict" });
    await verdict.getByRole("radio", { name: /Spoofed sender/ }).check();
    await verdict.getByRole("button", { name: "Check my conclusion" }).click();
    await expect(verdict.getByText(/Not yet\. Look again/)).toBeVisible();
    await verdict.getByLabel(/Raw message, line 4: Authentication-Results/).check();
    await verdict.getByLabel(/Raw message, line 6: Reply-To/).check();
    await audit(page, "evidence-concluding");
    await verdict.getByRole("button", { name: "Check my conclusion" }).click();
    await expect(verdict.getByText(/^Task 3, done:/)).toBeVisible();

    // Complete: the debrief appears, with what each piece of evidence meant and the bonus one missed.
    const debrief = page.getByRole("region", { name: "Debrief" });
    await expect(debrief).toBeVisible();
    await expect(debrief.getByText("DMARC fails on alignment", { exact: true })).toBeVisible();
    await expect(debrief.getByText(/Missed · bonus/)).toHaveCount(2);
    await expect(debrief.getByText("Block accounts-verify.example.net", { exact: false })).toBeVisible();
    // The verdict took two tries (the first had nothing cited), and the reader is taken to the debrief.
    await expect(debrief.getByText("Reached in 2 tries.")).toBeVisible();
    await expect(debrief.getByRole("heading", { name: "Debrief" })).toBeFocused();
    await audit(page, "evidence-debrief");

    // Undoing a mark afterwards does not take the debrief away.
    const undo = lines(page, raw).nth(3);
    await undo.click();
    await expect(undo).toHaveAttribute("aria-selected", "false");
    await expect(debrief).toBeVisible();

    // A reload keeps it; a reset takes it away again (the completion itself is kept).
    await page.reload();
    await expect(page.getByRole("region", { name: "Debrief" })).toBeVisible();
    await page.getByRole("button", { name: "Start over" }).click();
    await page.getByRole("dialog").getByRole("button", { name: "Start over" }).click();
    await expect(page.getByRole("region", { name: "Debrief" })).toHaveCount(0);
    await expect(page.getByText("DMARC fails on alignment")).toHaveCount(0);
  });
});
