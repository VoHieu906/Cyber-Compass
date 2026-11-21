import type { Page } from "@playwright/test";
import { expect, plain } from "./test";

type Choice = { id: number; label: string };
type ResultQuestion = {
  type: "single_choice" | "scenario" | "multi_choice" | "true_false" | "fill_blank" | "matching" | "ordering";
  position: number;
  payload: { options?: Choice[]; left?: Choice[]; right?: Choice[]; items?: Choice[]; blanks?: number };
  correct_answer: Record<string, unknown> | null;
};
export type ExerciseResult = { lesson: { title: string }; passed: boolean; questions: ResultQuestion[] };

/** A correct answer in display labels, so it still fits when a retry shuffles ids (D31). */
export type Solution =
  | { type: "choice"; labels: string[] }
  | { type: "true_false"; value: boolean }
  | { type: "blanks"; values: string[] }
  | { type: "matching"; pairs: [string, string][] }
  | { type: "ordering"; labels: string[] };

/** Reads the solutions the API reveals after a failed attempt (wrong and unanswered questions only). */
export function solutions(result: ExerciseResult): Solution[] {
  return [...result.questions]
    .sort((a, b) => a.position - b.position)
    .map((q) => {
      const key = q.correct_answer;
      if (!key) throw new Error(`question ${q.position} has no revealed answer`);
      const label = (list: Choice[] | undefined, id: number) => list!.find((c) => c.id === id)!.label;
      switch (q.type) {
        case "single_choice":
        case "scenario":
          return { type: "choice", labels: [label(q.payload.options, key.option as number)] };
        case "multi_choice":
          return { type: "choice", labels: (key.options as number[]).map((id) => label(q.payload.options, id)) };
        case "true_false":
          return { type: "true_false", value: key.value as boolean };
        case "fill_blank":
          return { type: "blanks", values: (key.blanks as string[][]).map((accepted) => accepted[0]) };
        case "matching":
          return {
            type: "matching",
            pairs: (key.matches as number[]).map(
              (right, i) => [q.payload.left![i].label, label(q.payload.right, right)] as [string, string],
            ),
          };
        case "ordering":
          return { type: "ordering", labels: (key.order as number[]).map((id) => label(q.payload.items, id)) };
      }
    });
}

/** Answers the current question through the real controls, by pointer and keyboard. */
export async function answer(page: Page, solution: Solution) {
  const main = page.getByRole("main");
  switch (solution.type) {
    case "choice":
      for (const l of solution.labels) await main.getByLabel(plain(l), { exact: true }).check();
      return;
    case "true_false":
      await main.getByRole("radio", { name: solution.value ? "True" : "False", exact: true }).check();
      return;
    case "blanks":
      for (const [i, value] of solution.values.entries()) {
        await main.getByLabel(`Blank ${i + 1} of ${solution.values.length}`).fill(value);
      }
      return;
    case "matching":
      for (const [left, right] of solution.pairs)
        await main.getByLabel(left, { exact: true }).selectOption({ label: right });
      return;
    case "ordering": {
      const rows = main.locator("ol[aria-labelledby] > li");
      // Each row reads "<position> <label>".
      const current = async () => (await rows.allInnerTexts()).map((t) => t.trim().replace(/^\d+\s*/, ""));
      for (const [target, label] of solution.labels.entries()) {
        for (;;) {
          const at = (await current()).findIndex((text) => text.startsWith(plain(label)));
          expect(at, `ordering item ${label}`).toBeGreaterThanOrEqual(0);
          if (at <= target) break;
          const up = main.getByRole("button", { name: `Move ${label} up`, exact: true });
          await up.focus();
          await page.keyboard.press("Enter");
          // Focus stays on the moved item (on its other button once it reaches the top).
          await expect
            .poll(() => page.evaluate(() => document.activeElement?.getAttribute("aria-label") ?? ""))
            .toMatch(/^Move .+ (up|down)$/);
          expect(await page.evaluate(() => document.activeElement?.getAttribute("aria-label"))).toContain(label);
        }
      }
      return;
    }
  }
}
