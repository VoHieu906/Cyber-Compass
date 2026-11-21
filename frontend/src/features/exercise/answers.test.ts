import { describe as group, expect, it } from "vitest";
import { answeredCount, describe, fits, isAnswered, toSubmission } from "./answers";
import type { ExerciseQuestion } from "./types";

const base = { prompt: "Q", section: null };
const single: ExerciseQuestion = { ...base, id: 1, position: 1, type: "single_choice", payload: { options: [{ id: 0, label: "A" }, { id: 1, label: "B" }] } };
const blanks: ExerciseQuestion = { ...base, id: 2, position: 2, type: "fill_blank", payload: { text: "___ and ___", blanks: 2 } };
const matching: ExerciseQuestion = {
  ...base,
  id: 3,
  position: 3,
  type: "matching",
  payload: { left: [{ id: 0, label: "TCP" }, { id: 1, label: "IP" }], right: [{ id: 0, label: "3" }, { id: 1, label: "4" }] },
};
const ordering: ExerciseQuestion = { ...base, id: 4, position: 4, type: "ordering", payload: { items: [{ id: 0, label: "b" }, { id: 1, label: "a" }] } };
const labels = { true: "True", false: "False", empty: "(blank)", or: "or" };

group("answers", () => {
  it("counts a question as answered once anything is chosen, typed or moved", () => {
    expect(isAnswered(single, undefined)).toBe(false);
    expect(isAnswered(single, { option: 0 })).toBe(true);
    expect(isAnswered(blanks, { blanks: [" ", ""] })).toBe(false);
    expect(isAnswered(blanks, { blanks: ["frame", ""] })).toBe(true);
    expect(isAnswered(matching, { matches: [null, null] })).toBe(false);
    expect(isAnswered(matching, { matches: [null, 1] })).toBe(true);
    expect(isAnswered(ordering, { order: [1, 0] })).toBe(true);
  });

  it("submits only answered questions", () => {
    const draft = { 1: { option: 1 }, 2: { blanks: ["", ""] }, 3: { matches: [1, 0] } };
    expect(toSubmission([single, blanks, matching, ordering], draft)).toEqual([
      { question_id: 1, response: { option: 1 } },
      { question_id: 3, response: { matches: [1, 0] } },
    ]);
    expect(answeredCount([single, blanks, matching, ordering], draft)).toBe(2);
  });

  it("describes responses and keys in words", () => {
    expect(describe(single, { option: 1 }, labels)).toEqual(["B"]);
    expect(describe(blanks, { blanks: ["frame", " "] }, labels)).toEqual(["frame", "(blank)"]);
    expect(describe(blanks, { blanks: [["packet", "datagram"], ["frame"]] }, labels)).toEqual(["packet or datagram", "frame"]);
    expect(describe(matching, { matches: [1, null] }, labels)).toEqual(["TCP → 4", "IP → (blank)"]);
    expect(describe(ordering, { order: [1, 0] }, labels)).toEqual(["a", "b"]);
    expect(describe(single, null, labels)).toEqual([]);
  });

  it("ignores a saved response that no longer fits its question", () => {
    expect(fits(single, { option: 7 })).toBe(false);
    expect(fits(blanks, { blanks: ["only one"] })).toBe(false);
    expect(fits(ordering, { order: [0, 0] })).toBe(false);
    expect(fits(single, { value: true })).toBe(false);
    expect(toSubmission([single], { 1: { option: 7 } })).toEqual([]);
  });
});
