import AxeBuilder from "@axe-core/playwright";
import type { Page, TestInfo } from "@playwright/test";
import { account } from "./support/accounts";
import { expect, headerOverflow, login, pageOverflow, test } from "./support/test";

/**
 * Responsive and accessibility sweep (Phase 7 Part E). Every route, at 1280, 768 and 390px, in
 * light and dark: no horizontal page overflow, a header that fits, and no serious or critical
 * axe violation (WCAG 2.2 A and AA rules). Lesser axe findings are attached to the report.
 * Public routes are also covered by Hallmark's sloplint render tier.
 */
const WIDTHS = [1280, 768, 390];
const AXE_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

type Finding = {
  route: string;
  theme: string;
  width: number;
  id: string;
  impact: string;
  nodes: number;
  help: string;
  target: string;
};

async function setTheme(page: Page, theme: "light" | "dark") {
  await page.evaluate((t) => localStorage.setItem("theme", t), theme);
}

/**
 * One pass per theme over all routes (light at 1280px first, dark at 390px first), so each page
 * loads twice in all: a real learner's 120-requests-a-minute API limit applies here too.
 */
async function sweep(page: Page, routes: string[], findings: Finding[]) {
  await page.goto(routes[0]);
  for (const theme of ["light", "dark"] as const) {
    // `next dev` mounts every page twice, doubling its API requests; between passes, let the real
    // 120-a-minute limit's window pass. A production build (E2E_PERF=1, CI) needs no pause.
    if (theme === "dark" && process.env.E2E_PERF !== "1") await page.waitForTimeout(61_000);
    await setTheme(page, theme);
    const widths = theme === "light" ? WIDTHS : [...WIDTHS].reverse();
    for (const route of routes) {
      await page.setViewportSize({ width: widths[0], height: 900 });
      await page.goto(route);
      await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
      await page.waitForLoadState("networkidle");

      const result = await new AxeBuilder({ page })
        .withTags(AXE_TAGS)
        // The 3D view is a canvas in its own sandboxed document; its text description is the accessible version (D22).
        .exclude('iframe[src^="/sandbox/"]')
        .analyze();
      for (const v of result.violations) {
        findings.push({
          route,
          theme,
          width: widths[0],
          id: v.id,
          impact: v.impact ?? "unknown",
          nodes: v.nodes.length,
          help: v.help,
          target: `${v.nodes[0]?.target.join(" ")} ${v.nodes[0]?.html.slice(0, 160)}`,
        });
      }

      for (const w of widths) {
        await page.setViewportSize({ width: w, height: 900 });
        await page.waitForTimeout(150);
        expect(await pageOverflow(page), `${route} ${theme} ${w}px page overflow`).toBeLessThanOrEqual(0);
        expect(await headerOverflow(page), `${route} ${theme} ${w}px header overflow`).toBeLessThanOrEqual(0);
      }
    }
  }
}

async function report(testInfo: TestInfo, findings: Finding[]) {
  await testInfo.attach("axe-findings.json", {
    body: JSON.stringify(findings, null, 2),
    contentType: "application/json",
  });
  if (findings.length) {
    console.log(
      findings
        .map(
          (f) => `${f.impact.padEnd(8)} ${f.id} (${f.nodes}) ${f.route} ${f.theme} ${f.width}px: ${f.help}
         ${f.target}`,
        )
        .join("\n"),
    );
  }
  const blocking = findings.filter((f) => f.impact === "serious" || f.impact === "critical");
  expect(blocking, "serious or critical axe violations").toEqual([]);
}

test.describe("responsive and accessibility sweep", () => {
  test.describe.configure({ timeout: 420_000 });

  test("public and sign-in routes", async ({ page }, testInfo) => {
    const findings: Finding[] = [];
    await sweep(
      page,
      [
        "/",
        "/courses",
        "/courses/networking-fundamentals",
        "/careers",
        "/careers/soc-analyst",
        "/blog",
        "/login",
        "/register",
        "/forgot-password",
        "/reset-password?token=t&email=someone%40example.test",
      ],
      findings,
    );
    await report(testInfo, findings);
  });

  test("learner routes", async ({ page }, testInfo) => {
    await login(page, account("sweep"));

    // Give the learner a result, a goal and a roadmap so those pages show their real content.
    await page.goto("/assessment");
    await page.getByRole("button", { name: "Start the assessment" }).click();
    await page.waitForURL(/\/assessment\/attempts\/\d+$/);
    const attempt = new URL(page.url()).pathname;
    await page.locator("fieldset").getByRole("radio").first().check();
    await expect(page.getByText("Answers saved", { exact: true })).toBeVisible();
    await page.getByRole("complementary").getByRole("button", { name: "Review and submit" }).click();
    await page.getByRole("button", { name: "Submit with unanswered questions" }).click();
    await page.waitForURL(/\/result$/);
    await page.goto("/careers/soc-analyst");
    await page.getByRole("button", { name: "Set as my goal" }).click();
    await expect(page.getByText("SOC Analyst is your career goal.", { exact: true })).toBeVisible();
    await page.goto("/roadmap");
    await page.getByRole("button", { name: "Build my roadmap" }).click();
    await expect(page.getByText("Stage 1").first()).toBeVisible();

    const findings: Finding[] = [];
    await sweep(page, ["/dashboard", "/assessment", `${attempt}/result`, "/roadmap", "/roadmap/map"], findings);
    // A fresh attempt, open on its first question.
    await page.goto("/assessment");
    await page.getByRole("button", { name: "Take it again" }).click();
    await page.waitForURL(/\/assessment\/attempts\/\d+$/);
    await sweep(page, [new URL(page.url()).pathname], findings);
    await report(testInfo, findings);
  });

  test("learning and settings routes", async ({ page }, testInfo) => {
    // A second learner: full page loads skip the client cache, and one account sweeping every
    // route would meet the real 120-a-minute API limit.
    await login(page, account("sweep2"));
    const findings: Finding[] = [];
    await sweep(
      page,
      [
        "/learn/intro-to-computer-networks",
        "/learn/osi-model/exercise",
        "/labs",
        "/labs/investigate-a-suspicious-login",
        "/notifications",
        "/settings/profile",
        "/settings/goals",
        "/settings/security",
        "/settings/preferences",
        "/settings/notifications",
        "/settings/shortcuts",
      ],
      findings,
    );
    await report(testInfo, findings);
  });

  test("admin routes", async ({ page }, testInfo) => {
    await login(page, account("admin"));
    await page.goto("/admin/users");
    const userHref = await page.getByRole("main").locator('a[href^="/admin/users/"]').first().getAttribute("href");
    await page.goto("/admin/lessons");
    const lessonHref = await page.getByRole("main").locator('a[href^="/admin/lessons/"]').first().getAttribute("href");

    const findings: Finding[] = [];
    await sweep(
      page,
      ["/admin", "/admin/users", userHref!, "/admin/audit", "/admin/lessons", lessonHref!, "/admin/courses"],
      findings,
    );
    await report(testInfo, findings);
  });
});

test("the first Tab offers a skip link that moves focus to the content", async ({ page }) => {
  await page.goto("/courses");
  await expect(page.getByRole("heading", { level: 1 }).first()).toBeVisible();
  await page.keyboard.press("Tab");
  const skip = page.getByRole("link", { name: "Skip to content" });
  await expect(skip).toBeFocused();
  await expect(skip).toBeVisible();
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(/#main$/);
  // The next Tab continues inside the content, not back in the header.
  await page.keyboard.press("Tab");
  expect(await page.evaluate(() => Boolean(document.activeElement?.closest("main")))).toBe(true);
});
