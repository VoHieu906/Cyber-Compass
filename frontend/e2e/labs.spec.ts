import type { Page } from "@playwright/test";
import { account } from "./support/accounts";
import { expect, signIn, test } from "./support/test";

/**
 * The shell lab on its server engine (D28, ARCHITECTURE §7.1): commands with pipes, the refusals
 * that keep the engine safe, answer checkpoints, completion, and the 409 a stale tab gets.
 */
const LAB = "/labs/investigate-a-suspicious-login";

async function run(page: Page, command: string): Promise<string> {
  const log = page.getByRole("log").locator("li");
  const before = await log.count();
  await page.locator("#lab-command").fill(command);
  await page.locator("#lab-command").press("Enter");
  await expect(log).toHaveCount(before + 1);
  return (await log.last().innerText()).trim();
}

async function answerTask(page: Page, task: string, value: string, correct = true) {
  const item = page.getByRole("complementary").locator("li").filter({ hasText: task });
  await item.getByRole("textbox").fill(value);
  await item.getByRole("button", { name: "Check" }).click();
  if (correct) await expect(item.getByText(/^Task \d+, done:/)).toBeVisible();
}

test.describe("labs", () => {
  // A second tab's stale action is answered with 409 on purpose.
  test.use({ allowedConsole: /status of 409/ });

  test("the shell lab runs safely on the server and completes", async ({ page, context }) => {
    await signIn(page, account("labs"));
    await page.goto("/labs");
    // The lab management spec may have published a lab of its own meanwhile, so at least the three seeded ones.
    const items = page.getByRole("main").locator("ol > li");
    await expect(items.first()).toBeVisible();
    expect(await items.count()).toBeGreaterThanOrEqual(3);

    await page.goto(LAB);
    await page.getByRole("button", { name: "Start the lab" }).click();
    await expect(page.locator("#lab-command")).toBeVisible();

    expect(await run(page, "cat notes.txt")).toContain("Ticket SEC-142");
    await run(page, "cd /var/log");
    expect(await run(page, 'grep 203.0.113.77 auth.log | grep -c "Failed password"')).toMatch(/24$/);
    expect(await run(page, "ls; rm -rf /")).toContain('does not support chaining commands with ";"');
    expect(await run(page, "cat auth.log > /tmp/x")).toContain("redirecting output");
    expect(await run(page, "rm notes.txt")).toContain("not available in this lab");
    // A nested-quantifier pattern runs under SafeRegex's limits and answers quickly (§7.1).
    const started = Date.now();
    expect(await run(page, "grep -E '(a+)+$' auth.log")).toContain("grep -E");
    expect(Date.now() - started).toBeLessThan(5_000);
    expect(await run(page, "grep update.sh syslog | head -n 1")).toContain("/tmp/.cache/update.sh");

    await answerTask(page, "Which IP address", "10.0.0.1", false);
    await expect(page.getByText("Not quite.").first()).toBeVisible();
    await answerTask(page, "Which IP address", "203.0.113.77");
    await answerTask(page, "Which account", "deploy");
    await answerTask(page, "How many failed", "24");
    await answerTask(page, "Which file did the attacker", "/tmp/.cache/update.sh");
    await expect(page.getByText("Lab complete").first()).toBeVisible();

    // Another tab acts on an older state version: it is refused and reloads the latest state.
    const stale = await context.newPage();
    await stale.goto(LAB);
    await expect(stale.locator("#lab-command")).toBeVisible();
    await page.bringToFront();
    await run(page, "pwd");
    await stale.bringToFront();
    await stale.locator("#lab-command").fill("whoami");
    await stale.locator("#lab-command").press("Enter");
    await expect(stale.getByText(/changed in another tab or window/)).toBeVisible();
    await expect(stale.getByRole("log").locator("li").last()).toContainText("pwd");
  });
});
