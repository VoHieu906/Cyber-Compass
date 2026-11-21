/**
 * Shapes of the exercise API (docs/API_SPECIFICATION.md#learning, ARCHITECTURE D31). Option,
 * item and right-item ids are display positions; the key never reaches the browser before
 * submission.
 */

export type Choice = { id: number; label: string };

type Base = { id: number; position: number; prompt: string; section: { id: string; heading: string | null } | null };

export type ExerciseQuestion = Base &
  (
    | { type: "single_choice"; payload: { options: Choice[] } }
    | { type: "scenario"; payload: { scenario: string; options: Choice[] } }
    | { type: "multi_choice"; payload: { options: Choice[] } }
    | { type: "true_false"; payload: Record<string, never> }
    | { type: "fill_blank"; payload: { text: string; blanks: number } }
    | { type: "matching"; payload: { left: Choice[]; right: Choice[] } }
    | { type: "ordering"; payload: { items: Choice[] } }
  );

export type QuestionType = ExerciseQuestion["type"];

/** What the learner sends, by type. */
export type Response =
  | { option: number }
  | { options: number[] }
  | { value: boolean }
  | { blanks: string[] }
  | { matches: (number | null)[] }
  | { order: number[] };

/** The key after submission: the response's shape, except blanks, which list every accepted text. */
export type CorrectAnswer = Exclude<Response, { blanks: string[] }> | { blanks: string[][] };

export type Exercise = {
  lesson: { slug: string; title: string };
  pass_percent: number;
  question_count: number;
  questions: ExerciseQuestion[];
  passed: boolean;
  best_score: number | null;
  attempt_count: number;
};

export type ResultQuestion = ExerciseQuestion & {
  response: Response | null;
  is_correct: boolean;
  /** Null when the answer was right (D31). */
  correct_answer: CorrectAnswer | null;
  explanation: string;
};

export type ExerciseResult = {
  id: number;
  lesson: { slug: string; title: string };
  score: number;
  passed: boolean;
  pass_percent: number;
  correct: number;
  total: number;
  submitted_at: string;
  lesson_status: "not_started" | "started" | "completed";
  questions: ResultQuestion[];
};

export type SubmittedAnswer = { question_id: number; response: Response | null };
