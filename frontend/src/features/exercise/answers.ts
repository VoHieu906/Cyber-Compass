import type { CorrectAnswer, ExerciseQuestion, Response, SubmittedAnswer } from "./types";

/** The learner's draft: a response per question id, absent until they touch the question. */
export type Draft = Record<number, Response>;

/**
 * Whether a saved response still fits its question. A draft can outlive a change to the
 * exercise (session storage), and a response of the wrong shape would be refused (422).
 */
export function fits(question: ExerciseQuestion, response: Response | undefined): response is Response {
  if (!response || typeof response !== "object") return false;
  switch (question.type) {
    case "single_choice":
    case "scenario":
      return "option" in response && question.payload.options.some((o) => o.id === response.option);
    case "multi_choice":
      return "options" in response && Array.isArray(response.options) && response.options.every((id) => question.payload.options.some((o) => o.id === id));
    case "true_false":
      return "value" in response && typeof response.value === "boolean";
    case "fill_blank":
      return "blanks" in response && Array.isArray(response.blanks) && response.blanks.length === question.payload.blanks;
    case "matching":
      return "matches" in response && Array.isArray(response.matches) && response.matches.length === question.payload.left.length;
    case "ordering":
      return "order" in response && Array.isArray(response.order) && [...response.order].sort().join() === question.payload.items.map((i) => i.id).sort().join();
  }
}

/** Whether a draft response counts as an answer (anything chosen, typed or moved). */
export function isAnswered(question: ExerciseQuestion, response: Response | undefined): boolean {
  if (!fits(question, response)) return false;
  if ("blanks" in response) return response.blanks.some((b) => b.trim() !== "");
  if ("matches" in response) return response.matches.some((m) => m !== null);
  if ("options" in response) return response.options.length > 0;
  return true;
}

export function answeredCount(questions: ExerciseQuestion[], draft: Draft): number {
  return questions.filter((q) => isAnswered(q, draft[q.id])).length;
}

/** Answered questions only; the server counts the rest as unanswered (D31). */
export function toSubmission(questions: ExerciseQuestion[], draft: Draft): SubmittedAnswer[] {
  return questions.filter((q) => isAnswered(q, draft[q.id])).map((q) => ({ question_id: q.id, response: draft[q.id] }));
}

/** The draft's response for a question, or undefined when there is none or it no longer fits. */
export function responseFor(question: ExerciseQuestion, draft: Draft): Response | undefined {
  const response = draft[question.id];
  return fits(question, response) ? response : undefined;
}

export type DescribeLabels = { true: string; false: string; empty: string; or: string };

/**
 * A response or an answer key in words, one line per part: the chosen labels, the typed blanks,
 * each matched pair, or the items in order. Used by the result to show "your answer" and
 * "correct answer" side by side.
 */
export function describe(question: ExerciseQuestion, value: Response | CorrectAnswer | null, labels: DescribeLabels): string[] {
  if (!value) return [];
  const label = (list: { id: number; label: string }[], id: number | null) => list.find((c) => c.id === id)?.label ?? labels.empty;

  switch (question.type) {
    case "single_choice":
    case "scenario":
      return "option" in value ? [label(question.payload.options, value.option)] : [];
    case "multi_choice":
      return "options" in value ? question.payload.options.filter((o) => value.options.includes(o.id)).map((o) => o.label) : [];
    case "true_false":
      return "value" in value ? [value.value ? labels.true : labels.false] : [];
    case "fill_blank":
      return "blanks" in value
        ? value.blanks.map((b: string | string[]) => (Array.isArray(b) ? b.join(` ${labels.or} `) : b.trim() || labels.empty))
        : [];
    case "matching":
      return "matches" in value
        ? question.payload.left.map((l, i) => `${l.label} → ${label(question.payload.right, value.matches[i] ?? null)}`)
        : [];
    case "ordering":
      return "order" in value ? value.order.map((id) => label(question.payload.items, id)) : [];
  }
}
