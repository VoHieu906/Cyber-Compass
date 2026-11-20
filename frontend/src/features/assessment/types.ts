/** Shapes of the assessment API (docs/API_SPECIFICATION.md#assessment-phase-4-part-a). */

export type Band = "gap" | "developing" | "strength";

export type DomainRef = { slug: string; name: string };

/** The scoring rules as the API states them (ARCHITECTURE §6.1). */
export type Formula = { weights: Record<"1" | "2" | "3", number>; unanswered: "incorrect"; developing_from: number; strength_from: number };

export type ActiveAssessment = {
  slug: string;
  title: string;
  description: string;
  time_limit_minutes: number | null;
  question_count: number;
  domains: (DomainRef & { question_count: number })[];
  formula: Formula;
  open_attempt: { id: number; answered: number; total: number } | null;
  latest_result: { id: number; score: number; band: Band; submitted_at: string } | null;
};

export type AttemptQuestion = {
  id: number;
  position: number;
  domain: DomainRef;
  difficulty: 1 | 2 | 3;
  prompt: string;
  code: string | null;
  options: { id: number; label: string }[];
  selected_option_id: number | null;
};

export type Attempt = {
  id: number;
  status: "in_progress" | "submitted";
  assessment: { slug: string; title: string };
  started_at: string;
  submitted_at: string | null;
  total: number;
  answered: number;
  questions: AttemptQuestion[];
};

export type SavedAnswer = { question_id: number; selected_option_id: number | null; answered: number };

export type ScoreRow = {
  score: number;
  band: Band;
  earned: number;
  possible: number;
  correct: number;
  answered: number;
  total: number;
};

export type ReviewItem = {
  question_id: number;
  position: number;
  domain: string;
  difficulty: 1 | 2 | 3;
  prompt: string;
  code: string | null;
  is_correct: boolean;
  your_option: { id: number; label: string } | null;
  /** Only for wrong or unanswered questions (D26). */
  correct_option: { id: number; label: string } | null;
  explanation: string | null;
};

export type NextStep = {
  kind: "course";
  reason: Exclude<Band, "strength">;
  domain: DomainRef;
  score: number;
  course: { slug: string; title: string };
};

export type AttemptResult = {
  id: number;
  assessment: { slug: string; title: string };
  started_at: string;
  submitted_at: string;
  overall: ScoreRow;
  domains: (ScoreRow & { domain: DomainRef })[];
  strengths: string[];
  gaps: string[];
  next_steps: NextStep[];
  formula: Formula;
  review: ReviewItem[];
};

export type AttemptSummary = {
  id: number;
  assessment: { slug: string; title: string };
  submitted_at: string;
  score: number;
  band: Band;
  domains: { slug: string; score: number; band: Band }[];
};
