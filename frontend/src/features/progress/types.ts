import type { Band } from "@/features/assessment/types";
import type { KnowledgeMapDomain } from "@/features/roadmap/types";

/** GET /progress/summary. Weeks start on Monday in the learner's timezone (D33). */
export type ProgressSummary = {
  enrolled_courses: number;
  completed_courses: number;
  completed_lessons: number;
  total_minutes: number;
  week: { starts_on: string; minutes: number; goal_minutes: number | null };
  streak: { current: number; longest: number; active_today: boolean; last_active_on: string | null };
  roadmap: { career: string; completion_percent: number } | null;
  timezone: string;
};

/** GET /progress/activity?range=Nw, oldest week first. */
export type WeeklyActivity = {
  timezone: string;
  lessons_completed_before: number;
  weeks: { starts_on: string; minutes: number; lessons_completed: number }[];
};

export type ActivityType = "lesson_started" | "lesson_completed" | "study_session" | "exercise_submitted" | "assessment_submitted" | "lab_completed";

export type ActivitySubject =
  | { kind: "lesson"; slug: string; title: string }
  | { kind: "lab"; slug: string; title: string }
  | { kind: "exercise"; lesson: { slug: string; title: string }; score: number; passed: boolean }
  | { kind: "assessment"; id: number; title: string; score: number | null };

/** GET /progress/recent, newest first. */
export type Activity = {
  id: number;
  type: ActivityType;
  occurred_at: string;
  minutes: number;
  subject: ActivitySubject | null;
};

export type NextLesson = { slug: string; title: string; estimated_minutes: number; topic?: string; course?: string };

/** GET /progress/next. */
export type NextLessons = { source: "roadmap" | "courses" | null; lessons: NextLesson[] };

/** GET /progress/skills: the knowledge map's domain rows. */
export type SkillDomain = KnowledgeMapDomain;

/** GET /progress/courses. */
export type CourseCompletion = {
  slug: string;
  title: string;
  domain: { slug: string; name: string } | null;
  enrolled_at: string;
  completed_at: string | null;
  completed_lessons: number;
  total_lessons: number;
  percent: number;
};

/** GET /progress/assessments, oldest first. */
export type AssessmentHistoryItem = {
  id: number;
  assessment: { slug: string; title: string };
  submitted_at: string;
  score: number;
  band: Band;
  domains: { slug: string; score: number; band: Band }[];
};

export const RANGES = ["4w", "12w", "26w", "52w"] as const;
export type Range = (typeof RANGES)[number];
