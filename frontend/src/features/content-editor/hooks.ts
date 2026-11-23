"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  createChapter,
  createLesson,
  createPost,
  fetchBlockTypes,
  fetchCourseGraph,
  fetchCourses,
  fetchLessons,
  fetchMedia,
  fetchPosts,
  lessonApi,
  postApi,
  renameChapter,
  reorderChapters,
  reorderLessons,
  uploadMedia,
  type DocumentApi,
} from "./api";
import type { AdminCourse, AdminCourseGraph, AdminLesson, AdminPost, LessonQuery, PostQuery } from "./types";

/** The block-document types the editor serves (D55). */
const APIS = { lesson: lessonApi, post: postApi };
export type DocKind = keyof typeof APIS;
export type DocOf<K extends DocKind> = K extends "lesson" ? AdminLesson : AdminPost;
const apiFor = <K extends DocKind>(kind: K) => APIS[kind] as unknown as DocumentApi<DocOf<K>>;

export const contentKeys = {
  all: ["admin", "content"] as const,
  lessons: (query: LessonQuery) => ["admin", "content", "lessons", query] as const,
  posts: (query: PostQuery) => ["admin", "content", "posts", query] as const,
  doc: (kind: DocKind, id: number) => ["admin", "content", kind, id] as const,
  revisions: (kind: DocKind, id: number) => ["admin", "content", "revisions", kind, id] as const,
  blockTypes: ["admin", "content", "block-types"] as const,
  courses: ["admin", "content", "courses"] as const,
  courseGraph: (courseId: number) => ["admin", "content", "course-graph", courseId] as const,
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
 * The document in the editor. Never refetched behind the editor's back (no focus refetch):
 * the form holds unsaved work, and lock_version decides what the server accepts.
 */
export function useEditorDoc<K extends DocKind>(kind: K, id: number) {
  return useQuery({
    queryKey: contentKeys.doc(kind, id),
    queryFn: ({ signal }) => apiFor(kind).fetch(id, signal),
    refetchOnWindowFocus: false,
    staleTime: Infinity,
  });
}

export function useRevisions(kind: DocKind, id: number, enabled: boolean) {
  return useQuery({
    queryKey: contentKeys.revisions(kind, id),
    queryFn: ({ signal }) => apiFor(kind).fetchRevisions(id, signal),
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

/** After any change: the editor gets the fresh document; lists and counts are refetched. */
function useDocMutation<K extends DocKind, V>(kind: K, id: number, fn: (variables: V) => Promise<DocOf<K>>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (doc) => {
      queryClient.setQueryData(contentKeys.doc(kind, id), doc);
      void queryClient.invalidateQueries({
        predicate: (q) =>
          q.queryKey[0] === "admin" &&
          !(q.queryKey[1] === "content" && (q.queryKey[2] === "lesson" || q.queryKey[2] === "post")),
      });
    },
  });
}

/** Every editing action of one document, through its type's API. */
export function useDocActions<K extends DocKind>(kind: K, id: number) {
  const api = apiFor(kind);
  const queryClient = useQueryClient();
  return {
    save: useDocMutation(kind, id, ({ copy, lockVersion }: { copy: DocOf<K>["working_copy"]; lockVersion: number }) =>
      api.saveDraft(id, copy, lockVersion),
    ),
    publish: useDocMutation(kind, id, ({ lockVersion, note }: { lockVersion: number; note: string }) =>
      api.publish(id, lockVersion, note),
    ),
    discard: useDocMutation(kind, id, () => api.discardDraft(id)),
    archive: useDocMutation(kind, id, () => api.archive(id)),
    unarchive: useDocMutation(kind, id, () => api.unarchive(id)),
    restore: useDocMutation(kind, id, ({ version, lockVersion }: { version: number; lockVersion: number }) =>
      api.restoreRevision(id, version, lockVersion),
    ),
    remove: useMutation({
      mutationFn: () => api.remove(id),
      onSuccess: () => {
        queryClient.removeQueries({ queryKey: contentKeys.doc(kind, id) });
        void queryClient.invalidateQueries({ queryKey: ["admin"] });
      },
    }),
  };
}

export function useCreateLesson() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createLesson,
    onSuccess: (lesson) => {
      queryClient.setQueryData(contentKeys.doc("lesson", lesson.id), lesson);
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

/** The course map panel (F3): the stored map, its lessons, issues and history. */
export function useCourseGraph(courseId: number) {
  return useQuery({ queryKey: contentKeys.courseGraph(courseId), queryFn: ({ signal }) => fetchCourseGraph(courseId, signal) });
}

/** Save or restore the course map; the panel gets the fresh state from the response. */
export function useCourseGraphMutation<V>(courseId: number, fn: (variables: V) => Promise<AdminCourseGraph>) {
  const queryClient = useQueryClient();
  return useMutation({ mutationFn: fn, onSuccess: (data) => queryClient.setQueryData(contentKeys.courseGraph(courseId), data) });
}

export function usePosts(query: PostQuery) {
  return useQuery({
    queryKey: contentKeys.posts(query),
    queryFn: ({ signal }) => fetchPosts(query, signal),
    placeholderData: keepPreviousData,
  });
}

export function useCreatePost() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: createPost,
    onSuccess: (post) => {
      queryClient.setQueryData(contentKeys.doc("post", post.id), post);
      void queryClient.invalidateQueries({ queryKey: ["admin"] });
    },
  });
}

export function useUploadMedia() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ file, alt }: { file: File; alt: string }) => uploadMedia(file, alt),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: contentKeys.media }),
  });
}
