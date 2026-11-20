"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  archiveLesson,
  createChapter,
  createLesson,
  deleteLesson,
  discardDraft,
  fetchBlockTypes,
  fetchCourses,
  fetchLesson,
  fetchLessons,
  fetchMedia,
  fetchRevisions,
  publishLesson,
  renameChapter,
  reorderChapters,
  reorderLessons,
  restoreRevision,
  saveDraft,
  uploadMedia,
} from "./api";
import type { AdminCourse, AdminLesson, LessonQuery, WorkingCopy } from "./types";

export const contentKeys = {
  all: ["admin", "content"] as const,
  lessons: (query: LessonQuery) => ["admin", "content", "lessons", query] as const,
  lesson: (id: number) => ["admin", "content", "lesson", id] as const,
  revisions: (id: number) => ["admin", "content", "revisions", id] as const,
  blockTypes: ["admin", "content", "block-types"] as const,
  courses: ["admin", "content", "courses"] as const,
  media: ["admin", "content", "media"] as const,
};

export function useLessons(query: LessonQuery) {
  return useQuery({
    queryKey: contentKeys.lessons(query),
    queryFn: ({ signal }) => fetchLessons(query, signal),
    placeholderData: keepPreviousData,
  });
}

/**
 * The lesson in the editor. Never refetched behind the editor's back (no focus refetch):
 * the form holds unsaved work, and lock_version decides what the server accepts.
 */
export function useAdminLesson(id: number) {
  return useQuery({
    queryKey: contentKeys.lesson(id),
    queryFn: ({ signal }) => fetchLesson(id, signal),
    refetchOnWindowFocus: false,
    staleTime: Infinity,
  });
}

export function useRevisions(id: number, enabled: boolean) {
  return useQuery({
    queryKey: contentKeys.revisions(id),
    queryFn: ({ signal }) => fetchRevisions(id, signal),
    enabled,
  });
}

export function useBlockTypes() {
  return useQuery({
    queryKey: contentKeys.blockTypes,
    queryFn: ({ signal }) => fetchBlockTypes(signal),
    staleTime: Infinity,
  });
}

export function useAdminCourses() {
  return useQuery({ queryKey: contentKeys.courses, queryFn: ({ signal }) => fetchCourses(signal) });
}

export function useMediaLibrary(enabled: boolean) {
  return useQuery({ queryKey: contentKeys.media, queryFn: ({ signal }) => fetchMedia(signal), enabled });
}

/** After any lesson change: the editor gets the fresh lesson; lists and counts are refetched. */
function useLessonMutation<V>(id: number, fn: (variables: V) => Promise<AdminLesson>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (lesson) => {
      queryClient.setQueryData(contentKeys.lesson(id), lesson);
      void queryClient.invalidateQueries({
        predicate: (q) => q.queryKey[0] === "admin" && !(q.queryKey[1] === "content" && q.queryKey[2] === "lesson"),
      });
    },
  });
}

export const useSaveDraft = (id: number) =>
  useLessonMutation(id, ({ copy, lockVersion }: { copy: WorkingCopy; lockVersion: number }) =>
    saveDraft(id, copy, lockVersion),
  );
export const usePublish = (id: number) =>
  useLessonMutation(id, ({ lockVersion, note }: { lockVersion: number; note: string }) =>
    publishLesson(id, lockVersion, note),
  );
export const useDiscardDraft = (id: number) => useLessonMutation(id, () => discardDraft(id));
export const useArchive = (id: number) => useLessonMutation(id, () => archiveLesson(id));
export const useRestoreRevision = (id: number) =>
  useLessonMutation(id, ({ version, lockVersion }: { version: number; lockVersion: number }) =>
    restoreRevision(id, version, lockVersion),
  );

export function useDeleteLesson(id: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => deleteLesson(id),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: contentKeys.lesson(id) });
      void queryClient.invalidateQueries({ queryKey: ["admin"] });
    },
  });
}

export function useCreateLesson() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createLesson,
    onSuccess: (lesson) => {
      queryClient.setQueryData(contentKeys.lesson(lesson.id), lesson);
      void queryClient.invalidateQueries({ queryKey: ["admin"] });
    },
  });
}

/** Structure changes return the whole course; it replaces that course in the cached list. */
function useCourseMutation<V>(fn: (variables: V) => Promise<AdminCourse>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (course) => {
      queryClient.setQueryData<AdminCourse[]>(contentKeys.courses, (all) =>
        all?.map((c) => (c.id === course.id ? course : c)),
      );
      void queryClient.invalidateQueries({
        predicate: (q) => q.queryKey[0] === "admin" && q.queryKey[2] !== "courses",
      });
    },
  });
}

export const useCreateChapter = () =>
  useCourseMutation(({ courseId, title }: { courseId: number; title: string }) => createChapter(courseId, title));
export const useRenameChapter = () =>
  useCourseMutation(({ chapterId, title }: { chapterId: number; title: string }) => renameChapter(chapterId, title));
export const useReorderChapters = () =>
  useCourseMutation(({ courseId, ids }: { courseId: number; ids: number[] }) => reorderChapters(courseId, ids));
export const useReorderLessons = () =>
  useCourseMutation(({ chapterId, ids }: { chapterId: number; ids: number[] }) => reorderLessons(chapterId, ids));

export function useUploadMedia() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ file, alt }: { file: File; alt: string }) => uploadMedia(file, alt),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: contentKeys.media }),
  });
}
