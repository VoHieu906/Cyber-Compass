/** Content authoring API shapes (backend app/Modules/Content/Http/Resources). */

import type { LessonSection } from "@/features/learning/types";

export type LessonStatus = "draft" | "published" | "archived";

/** A section as the editor holds it. `id` is absent for a section the server has not named yet. */
export type EditorSection = {
  id?: string;
  type: string;
  heading: string | null;
  payload: Record<string, unknown>;
};

export type Resource = { label: string; url: string };

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
};

export type AdminLesson = {
  id: number;
  slug: string;
  status: LessonStatus;
  published_at: string | null;
  slug_editable: boolean;
  has_unpublished_changes: boolean;
  /** Send back with every change; 0 while there is no draft. A stale value gets 409. */
  lock_version: number;
  draft: { updated_at: string | null; updated_by: string | null } | null;
  course: { id: number; slug: string; title: string; is_published: boolean };
  chapter: { id: number; title: string };
  topics: { slug: string; title: string }[];
  can_delete: boolean;
  working_copy: WorkingCopy;
  /** The last saved working copy, serialized exactly as learners receive it. */
  preview_sections: LessonSection[];
};

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
  editor: "form" | "json";
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
