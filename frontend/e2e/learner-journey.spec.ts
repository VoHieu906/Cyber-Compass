import { answer, solutions, type ExerciseResult } from "./support/exercise";
import { apiGet, expect, test } from "./support/test";

/**
 * The critical path from the brief, as one new learner: register, take the assessment, choose a
 * career, build the roadmap, learn a lesson through its exercise, and see it on the dashboard and
 * in the notification bell. Every step goes through the real UI and API; the exercise is passed
 * with the answers the API reveals after a failed attempt (D31), never with a key from the server.
 */
const LESSON = "osi-model";
const CAREER = "soc-analyst";

test("a new learner goes from sign-up to a completed lesson on the dashboard", async ({ page }) => {
  test.setTimeout(240_000);

  await test.step("register and land on the dashboard", async () => {
    await page.goto("/register");
    await page.getByLabel("Full name").fill("Journey Learner");
    // e2e-*@example.test, so the run's teardown removes it.
    await page.getByLabel("Email").fill(`e2e-journey-${Date.now()}@example.test`);
    await page.getByLabel("Password", { exact: true }).fill("Journey2026run");
    await page.getByLabel("Confirm password").fill("Journey2026run");
    await page.getByRole("button", { name: "Create account" }).click();
    await page.waitForURL("/dashboard");
    await expect(page.getByRole("heading", { name: "Welcome back, Journey", exact: true })).toBeVisible();
    await expect(page.getByRole("main").getByRole("link", { name: "Take the assessment" })).toBeVisible();
  });

  await test.step("take the assessment and see the result", async () => {
    await page.goto("/assessment");
    await page.getByRole("button", { name: "Start the assessment" }).click();
    await page.waitForURL(/\/assessment\/attempts\/\d+$/);
    await expect(page.getByText("Question 1 of 40")).toBeVisible();
    await page.locator("fieldset").getByRole("radio").first().check();
    await expect(page.getByText("Answers saved", { exact: true })).toBeVisible();
    await page.getByRole("complementary").getByRole("button", { name: "Review and submit" }).click();
    await page.getByRole("button", { name: "Submit with unanswered questions" }).click();
    await page.waitForURL(/\/result$/);
    await expect(page.getByText("Overall score", { exact: true })).toBeVisible();
    await expect(page.locator("tbody tr")).toHaveCount(8);
  });

  await test.step("choose a career goal and build the roadmap", async () => {
    await page.goto(`/careers/${CAREER}`);
    await page.getByRole("button", { name: "Set as my goal" }).click();
    await expect(page.getByText("SOC Analyst is your career goal.", { exact: true })).toBeVisible();

    await page.goto("/roadmap");
    await page.getByRole("button", { name: "Build my roadmap" }).click();
    await expect(page.getByText("Stage 1").first()).toBeVisible();
    await expect(page.getByText(/Based on an assessment score of \d+%/)).toBeVisible();
  });

  let lessonTitle = "";
  await test.step("fail the exercise, read the revealed answers, then pass it", async () => {
    await page.goto(`/learn/${LESSON}`);
    await page.getByRole("main").getByRole("link", { name: "Take the exercise" }).click();
    await page.waitForURL(`/learn/${LESSON}/exercise`);
    await expect(page.getByText(/^Question 1 of \d+$/)).toBeVisible();
    expect(await page.content(), "no answer key before submission").not.toContain('"correct_answer"');

    await page.getByRole("button", { name: "Review and submit" }).first().click();
    const failed = page.waitForResponse(
      (r) => r.url().endsWith(`/lessons/${LESSON}/exercise/attempts`) && r.request().method() === "POST",
    );
    await page.getByRole("button", { name: "Submit anyway" }).click();
    const result = ((await (await failed).json()) as { data: ExerciseResult }).data;
    expect(result.passed).toBe(false);
    await expect(page.getByText("Not passed yet: 0%")).toBeVisible();
    lessonTitle = result.lesson.title;

    const answers = solutions(result);
    await page.getByRole("button", { name: "Try again" }).click();
    await expect(page.getByText(`0 of ${answers.length} answered`)).toBeVisible();
    for (const [i, solution] of answers.entries()) {
      await expect(page.getByText(`Question ${i + 1} of ${answers.length}`, { exact: true })).toBeVisible();
      await answer(page, solution);
      const last = i === answers.length - 1;
      await page
        .getByRole("main")
        .getByRole("button", { name: last ? "Review and submit" : "Next", exact: true })
        .last()
        .click();
    }
    await expect(page.getByText("Every question has an answer")).toBeVisible();
    await page.getByRole("button", { name: "Submit answers" }).click();
    await expect(page.getByText("Passed with 100%")).toBeVisible();
    await expect(page.getByText("The lesson is complete.")).toBeVisible();
  });

  await test.step("the roadmap and the dashboard show the progress", async () => {
    await page.goto("/roadmap");
    await expect(page.getByText("In progress").first()).toBeVisible();

    await page.goto("/dashboard");
    const main = page.getByRole("main");
    await expect(main.getByText(lessonTitle).first()).toBeVisible();
    await expect(main.getByRole("link", { name: "Start" }).first()).toBeVisible();
  });

  await test.step("the bell counts the queued notifications", async () => {
    // Notifications go through the queue worker: wait on the API until the lesson's has arrived
    // (the assessment result's usually comes first), then let the bell show them.
    await expect
      .poll(async () => (await apiGet<{ type: string }[]>(page, "/api/v1/notifications")).map((n) => n.type), {
        timeout: 30_000,
      })
      .toContain("lesson_completed");
    await page.reload();
    const bell = page.getByRole("button", { name: /^Notifications/ });
    await expect(bell).toHaveAccessibleName(/^Notifications, \d+ unread$/);
    await bell.click();
    await expect(page.getByRole("link", { name: "See all notifications" })).toBeVisible();
    await expect(
      page.getByText(new RegExp(`You completed.*${lessonTitle.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`)).first(),
    ).toBeVisible();
    await page.keyboard.press("Escape");
    await expect(bell).toBeFocused();
  });
});
