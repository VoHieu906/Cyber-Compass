import fs from "node:fs";
import path from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { expect, type Page } from "@playwright/test";
import { headerOverflow, pageOverflow } from "./test";

const AXE_TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

/**
 * Checks the state a page is in right now, at 1280 and 390px and in both themes: no horizontal
 * overflow, a header that fits, and no serious or critical axe violation. It works on a page that
 * is mid-flow (a dialog open, a notice showing), which the route sweep cannot reach. With
 * E2E_SHOTS set to a folder it also saves a screenshot of every combination there.
 */
export async function audit(page: Page, label: string) {
  const original = page.viewportSize() ?? { width: 1280, height: 900 };
  const wasDark = await page.evaluate(() => document.documentElement.classList.contains("dark"));
  const shots = process.env.E2E_SHOTS;
  if (shots) fs.mkdirSync(shots, { recursive: true });
  try {
    for (const dark of [false, true]) {
      await page.evaluate((d) => document.documentElement.classList.toggle("dark", d), dark);
      for (const width of [1280, 390]) {
        await page.setViewportSize({ width, height: 900 });
        await page.waitForTimeout(200);
        const where = `${label} ${dark ? "dark" : "light"} ${width}px`;
        expect(await pageOverflow(page), `${where} page overflow`).toBeLessThanOrEqual(0);
        expect(await headerOverflow(page), `${where} header overflow`).toBeLessThanOrEqual(0);
        if (shots)
          await page.screenshot({
            path: path.join(shots, `${label}-${dark ? "dark" : "light"}-${width}.png`),
            fullPage: true,
          });
        if (width === 1280) {
          const result = await new AxeBuilder({ page }).withTags(AXE_TAGS).analyze();
          const blocking = result.violations
            .filter((v) => v.impact === "serious" || v.impact === "critical")
            .map((v) => `${v.id}: ${v.help} (${v.nodes[0]?.target.join(" ")} ${v.nodes[0]?.html.slice(0, 100)})`);
          expect(blocking, `${where} axe`).toEqual([]);
        }
      }
    }
  } finally {
    await page.evaluate((d) => document.documentElement.classList.toggle("dark", d), wasDark);
    await page.setViewportSize(original);
  }
}
