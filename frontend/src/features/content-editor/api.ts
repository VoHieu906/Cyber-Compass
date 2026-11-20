import type { Paginated } from "@/features/catalog/types";
import { api, request } from "@/lib/api/client";
import { lessonQueryToApi } from "./query-state";
import type {
  AdminCourse,
  AdminLesson,
  AdminLessonListItem,
  BlockTypeInfo,
  LessonQuery,
  Media,
  Revision,
  WorkingCopy,
} from "./types";

const base = "/api/v1/admin/content";

export const fetchLessons = (query: LessonQuery, signal?: AbortSignal) =>
  request<Paginated<AdminLessonListItem>>("GET", `${base}/lessons`, { query: lessonQueryToApi(query), signal });

export const fetchLesson = (id: number, signal?: AbortSignal) =>
  api.get<AdminLesson>(`${base}/lessons/${id}`, { signal });

export const createLesson = (input: { chapter_id: number; title: string; slug: string }) =>
  api.post<AdminLesson>(`${base}/lessons`, input);

export const saveDraft = (id: number, copy: WorkingCopy, lockVersion: number) =>
  api.put<AdminLesson>(`${base}/lessons/${id}/draft`, { ...copy, lock_version: lockVersion });

export const discardDraft = (id: number) => api.delete<AdminLesson>(`${base}/lessons/${id}/draft`);

export const publishLesson = (id: number, lockVersion: number, note: string) =>
  api.post<AdminLesson>(`${base}/lessons/${id}/publish`, { lock_version: lockVersion, note: note.trim() || undefined });

export const archiveLesson = (id: number) => api.post<AdminLesson>(`${base}/lessons/${id}/archive`);

export const deleteLesson = (id: number) => api.delete<void>(`${base}/lessons/${id}`);

export const fetchRevisions = (id: number, signal?: AbortSignal) =>
  api.get<Revision[]>(`${base}/lessons/${id}/revisions`, { signal });

export const restoreRevision = (id: number, version: number, lockVersion: number) =>
  api.post<AdminLesson>(`${base}/lessons/${id}/revisions/${version}/restore`, { lock_version: lockVersion });

export const fetchBlockTypes = (signal?: AbortSignal) => api.get<BlockTypeInfo[]>(`${base}/block-types`, { signal });

export const fetchCourses = (signal?: AbortSignal) => api.get<AdminCourse[]>(`${base}/courses`, { signal });

export const createChapter = (courseId: number, title: string) =>
  api.post<AdminCourse>(`${base}/courses/${courseId}/chapters`, { title });

export const renameChapter = (chapterId: number, title: string) =>
  api.patch<AdminCourse>(`${base}/chapters/${chapterId}`, { title });

export const reorderChapters = (courseId: number, chapterIds: number[]) =>
  api.put<AdminCourse>(`${base}/courses/${courseId}/chapters/order`, { chapter_ids: chapterIds });

export const reorderLessons = (chapterId: number, lessonIds: number[]) =>
  api.put<AdminCourse>(`${base}/chapters/${chapterId}/lessons/order`, { lesson_ids: lessonIds });

export const fetchMedia = (signal?: AbortSignal) => request<Paginated<Media>>("GET", `${base}/media`, { signal });

export function uploadMedia(file: File, alt: string) {
  const body = new FormData();
  body.append("image", file);
  body.append("alt", alt);
  return api.post<Media>(`${base}/media`, body);
}
