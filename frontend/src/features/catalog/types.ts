import type { CourseMapData } from "@/features/course-map/types";

export type Difficulty = "beginner" | "intermediate" | "advanced";

export type DomainRef = { slug: string; name: string };

export type Domain = DomainRef & { description: string; course_count: number };

/** A course as shown on a catalog card (backend CourseSummaryResource). */
export type CourseSummary = {
  slug: string;
  title: string;
  summary: string;
  domain: DomainRef;
  difficulty: Difficulty;
  lesson_count: number;
  estimated_minutes: number;
  thumbnail_url: string | null;
  published_at: string | null;
  /** Only present for a signed-in viewer. */
  is_favorited?: boolean;
};

export type CourseDetail = CourseSummary & {
  description: string;
  objectives: string[];
  skills: { slug: string; name: string }[];
  prerequisites: { slug: string; title: string }[];
  chapters: {
    title: string;
    position: number;
    lessons: { slug: string; title: string; summary: string; estimated_minutes: number }[];
  }[];
  related: CourseSummary[];
  /** The course map (F3), or null when the course has none. */
  graph: CourseMapData | null;
};

export type LessonStatus = "not_started" | "started" | "completed";

/** The signed-in learner's state in one course (GET /courses/{slug}/progress). */
export type CourseProgress = {
  enrolled: boolean;
  enrolled_at: string | null;
  completed_at: string | null;
  favorited: boolean;
  completed_lessons: number;
  total_lessons: number;
  percent: number;
  next_lesson: { slug: string; title: string } | null;
  lessons: Record<string, LessonStatus>;
};

export type Paginated<T> = {
  data: T[];
  meta: { current_page: number; last_page: number; per_page: number; total: number };
};

export const SORTS = ["newest", "title", "-title", "difficulty", "-difficulty", "duration", "-duration"] as const;
export type Sort = (typeof SORTS)[number];

export type CatalogQuery = {
  q: string;
  domain: string;
  difficulty: Difficulty | "";
  favorited: boolean;
  sort: Sort;
  page: number;
};
