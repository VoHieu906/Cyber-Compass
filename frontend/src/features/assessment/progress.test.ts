import { describe, expect, it } from "vitest";
import { answeredCount, domainProgress, firstUnanswered, percent, selectionsFrom } from "./progress";
import type { AttemptQuestion } from "./types";

function question(id: number, domain: string, selected: number | null = null): AttemptQuestion {
  return {
    id,
    position: id,
    domain: { slug: domain, name: domain.toUpperCase() },
    difficulty: 1,
    prompt: `Q${id}`,
    code: null,
    options: [{ id: id * 10, label: "a" }, { id: id * 10 + 1, label: "b" }],
    selected_option_id: selected,
  };
}

const questions = [question(1, "net", 10), question(2, "net"), question(3, "linux"), question(4, "linux", 41)];

describe("assessment progress", () => {
  it("overlays local choices on saved answers, including cleared ones", () => {
    const selections = selectionsFrom(questions, { 2: 21, 4: null });
    expect(selections).toEqual({ 1: 10, 2: 21, 3: null, 4: null });
    expect(answeredCount(questions, selections)).toBe(2);
  });

  it("resumes at the first unanswered question, or the start when all are answered", () => {
    expect(firstUnanswered(questions, selectionsFrom(questions, {}))).toBe(1);
    expect(firstUnanswered(questions, { 1: 10, 2: 20, 3: 30, 4: 40 })).toBe(0);
  });

  it("groups questions by domain in the order asked", () => {
    expect(domainProgress(questions, selectionsFrom(questions, {}))).toEqual([
      { slug: "net", name: "NET", start: 0, total: 2, answered: 1 },
      { slug: "linux", name: "LINUX", start: 2, total: 2, answered: 1 },
    ]);
  });

  it("rounds percentages and treats an empty whole as zero", () => {
    expect(percent(1, 3)).toBe(33);
    expect(percent(0, 0)).toBe(0);
  });
});
