import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { expect, it } from "vitest";

/*
 * The AI authoring guide (F6, D71) includes sections 1, 2, 3 and 5 of CONTENT_AUTHORING_GUIDE word
 * for word, one heading level deeper, so a chatbot gets the same rules as human authors. The api
 * container cannot read docs/, so the guide's source holds a copy and this test keeps it identical.
 * When it fails, copy the section from the doc into the source and run content:format-guide.
 */
const root = resolve(__dirname, "../../../..");
const doc = readFileSync(resolve(root, "docs/CONTENT_AUTHORING_GUIDE.md"), "utf8");
const source = readFileSync(resolve(root, "backend/resources/content-format/AI_AUTHORING_GUIDE.source.md"), "utf8");

const docSection = (n: string) => {
  const match = new RegExp(`^## ${n}\\. [\\s\\S]*?(?=^## |(?![\\s\\S]))`, "m").exec(doc);
  return match![0].trimEnd();
};
const included = (n: string) => {
  const match = new RegExp(
    `<!-- include: CONTENT_AUTHORING_GUIDE §${n} -->\\n([\\s\\S]*?)\\n<!-- end include -->`,
  ).exec(source);
  return match?.[1] ?? "";
};
const demote = (text: string) => text.replace(/^(#+) /gm, "#$1 ");

it.each(["1", "2", "3", "5"])("includes CONTENT_AUTHORING_GUIDE §%s word for word", (n) => {
  expect(included(n)).toBe(demote(docSection(n)));
});
