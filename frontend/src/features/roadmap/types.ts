import type { NodeStatus } from "./components/node-status";

/** Shapes of the roadmap API (docs/API_SPECIFICATION.md#roadmap). Status is computed by the API. */

export type ReasonKey = "career" | "gap" | "strength" | "foundation";

export type PathLesson = {
  slug: string;
  title: string;
  estimated_minutes: number;
  is_required: boolean;
  status: "not_started" | "started" | "completed";
};

export type PathNode = {
  topic: { slug: string; title: string; summary: string; level: 1 | 2 | 3; domain: { slug: string; name: string } };
  stage: number;
  position: number;
  recommendation: "required" | "review";
  reason: { key: ReasonKey; params: Record<string, string | number> };
  status: NodeStatus;
  percent: number;
  /** Slugs of prerequisite topics that are part of this path. */
  requires: string[];
  lessons: PathLesson[];
};

export type LearningPath = {
  id: number;
  version: number;
  status: "active" | "archived";
  generated_at: string;
  career: { slug: string; title: string };
  assessment: { attempt_id: number; score: number; submitted_at: string } | null;
  completion_percent: number;
  estimate: { remaining_minutes: number; weekly_minutes: number | null; weeks: number | null };
  next_lesson: { slug: string; title: string; topic: string } | null;
  outdated: ("career_changed" | "new_assessment")[];
  nodes: PathNode[];
  edges: { from: string; to: string }[];
};

export type PathVersion = {
  id: number;
  version: number;
  status: "active" | "archived";
  generated_at: string;
  career: { slug: string; title: string };
  assessment_score: number | null;
  topic_count: number;
};
