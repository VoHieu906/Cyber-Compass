/** Content authoring API shapes (backend app/Modules/Content/Http/Resources). */

import type { LessonSection } from "@/features/learning/types";
import type { Architecture, CourseGraph, ShapeId } from "@/lib/graph/shapes/schemas";

export type LessonStatus = "draft" | "published" | "archived";

/** A section as the editor holds it. `id` is absent for a section the server has not named yet. */
export type EditorSection = {
  id?: string;
  type: string;
  heading: string | null;
  payload: Record<string, unknown>;
};

export type Resource = { label: string; url: string };

/** The fields every block document's working copy has (lessons and posts, D55). */
export type BaseCopy = {
  slug: string;
  title: string;
  summary: string;
  estimated_minutes: number;
  sections: EditorSection[];
};

/** A block document in the editor (AdminLessonResource, AdminPostResource): the shared shape. */
export type EditorDoc<C extends BaseCopy = BaseCopy> = {
  id: number;
  slug: string;
  status: LessonStatus;
  published_at: string | null;
  slug_editable: boolean;
  has_unpublished_changes: boolean;
  /** Send back with every change; 0 while there is no draft. A stale value gets 409. */
  lock_version: number;
  draft: { updated_at: string | null; updated_by: string | null } | null;
  can_delete: boolean;
  working_copy: C;
  /** The last saved working copy, serialized exactly as readers receive it. */
  preview_sections: LessonSection[];
};

/** The lesson being edited: the draft if one exists, otherwise the live lesson. */
export type WorkingCopy = {
  chapter_id: number;
  slug: string;
  title: string;
  summary: string;
  estimated_minutes: number;
  objectives: string[];
  key_takeaways: string[];
  resources: Resource[];
  sections: EditorSection[];
  /** The lesson's architecture (F3, D57), validated with these sections; null when none. */
  architecture: Architecture | null;
};

export type AdminLesson = EditorDoc<WorkingCopy> & {
  course: { id: number; slug: string; title: string; is_published: boolean };
  chapter: { id: number; title: string };
  topics: { slug: string; title: string }[];
  /** Section id => exercise questions ("lesson/key") that point at it (D30): removing one is refused on publish. */
  exercise_references: Record<string, string[]>;
  /** Lessons the architecture's parts link to that readers will not see (not live in this course). */
  architecture_issues: string[];
};

/** The course map panel (GET /admin/content/courses/{id}/graph, F3, D58). */
export type AdminCourseGraph = {
  course: { id: number; slug: string; title: string };
  graph: CourseGraph | null;
  lock_version: number;
  owned_by_admin: boolean;
  lessons: { slug: string; title: string; status: LessonStatus; problem: string | null; shape: ShapeId | null; architecture: Architecture | null }[];
  issues: { type: "unplaced" | "unconnected" | "dangling" | "link_outside" | "no_problem"; lesson: string; detail?: string }[];
  history: { id: number; action: "course_graph.saved" | "course_graph.restored"; at: string | null; actor: string | null; has_before: boolean }[];
};

/** A blog post's working copy (F2). The reading time is computed by the API, never typed. */
export type PostCopy = BaseCopy & { cover: string | null; tags: string[] };

export type AdminPost = EditorDoc<PostCopy> & {
  cover: { id: string; url: string; alt: string; width: number | null; height: number | null } | null;
};

export type AdminPostListItem = {
  id: number;
  slug: string;
  title: string;
  status: LessonStatus;
  has_unpublished_changes: boolean;
  tags: string[];
  section_count: number;
  published_at: string | null;
  last_edited_at: string | null;
  last_edited_by: string | null;
};

export type PostQuery = { q: string; status: LessonStatus | ""; page: number };

export type AdminLessonListItem = {
  id: number;
  slug: string;
  title: string;
  status: LessonStatus;
  has_unpublished_changes: boolean;
  course: { id: number; title: string };
  chapter: { id: number; title: string; position: number };
  position: number;
  section_count: number;
  visual_count: number;
  published_at: string | null;
  last_edited_at: string | null;
  last_edited_by: string | null;
};

export type BlockTypeInfo = {
  key: string;
  label: string;
  description: string;
  /** form: generated fields; json: validated JSON editor; scene / script: the 3D editors with live preview. */
  editor: "form" | "json" | "scene" | "script";
  schema: Record<string, unknown>;
  example: Record<string, unknown>;
  diagram_keys?: string[];
};

export type AdminCourse = {
  id: number;
  slug: string;
  title: string;
  is_published: boolean;
  chapters: {
    id: number;
    title: string;
    position: number;
    lessons: {
      id: number;
      slug: string;
      title: string;
      status: LessonStatus;
      has_unpublished_changes: boolean;
      position: number;
    }[];
  }[];
};

export type Revision = {
  version: number;
  title: string | null;
  note: string | null;
  author: string | null;
  created_at: string | null;
  section_count: number;
  changes: {
    initial: boolean;
    fields: string[];
    sections: { added: number; removed: number; edited: number; reordered: boolean };
  } | null;
};

export type Media = {
  id: string;
  url: string;
  alt: string;
  width: number;
  height: number;
  size_bytes: number;
  created_at: string | null;
};

export const LESSON_SORTS = ["-updated_at", "course", "title"] as const;
export type LessonSort = (typeof LESSON_SORTS)[number];

export type LessonQuery = {
  q: string;
  course: number | null;
  status: LessonStatus | "";
  drafts: boolean;
  sort: LessonSort;
  page: number;
};
