"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useRef } from "react";
import { catalogKeys } from "@/features/catalog/hooks";
import { completeLesson, fetchLesson, startLesson } from "./api";
import type { Lesson } from "./types";

export const lessonKey = (slug: string) => ["lessons", slug] as const;

export function useLesson(slug: string) {
  return useQuery({ queryKey: lessonKey(slug), queryFn: ({ signal }) => fetchLesson(slug, signal) });
}

/** Records the lesson as started once per visit; failures are harmless and not shown. */
export function useStartLesson(lesson: Lesson | undefined) {
  const queryClient = useQueryClient();
  const started = useRef<string | null>(null);
  useEffect(() => {
    if (!lesson || lesson.progress.status !== "not_started" || started.current === lesson.slug) return;
    started.current = lesson.slug;
    startLesson(lesson.slug)
      .then(() => queryClient.invalidateQueries({ queryKey: ["roadmap"] }))
      .catch(() => undefined);
  }, [lesson, queryClient]);
}

/** Time on the page since it opened, in whole seconds. Paused while the tab is hidden. */
export function useTimeOnPage(key: string) {
  const elapsed = useRef(0);
  const since = useRef<number | null>(null);

  useEffect(() => {
    elapsed.current = 0;
    since.current = document.visibilityState === "visible" ? Date.now() : null;
    const onVisibility = () => {
      if (document.visibilityState === "hidden" && since.current !== null) {
        elapsed.current += Date.now() - since.current;
        since.current = null;
      } else if (document.visibilityState === "visible" && since.current === null) {
        since.current = Date.now();
      }
    };
    document.addEventListener("visibilitychange", onVisibility);
    return () => document.removeEventListener("visibilitychange", onVisibility);
  }, [key]);

  return () => Math.round((elapsed.current + (since.current !== null ? Date.now() - since.current : 0)) / 1000);
}

export function useCompleteLesson(lesson: Lesson) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (seconds: number) => completeLesson(lesson.slug, seconds),
    onSuccess: (result) => {
      queryClient.setQueryData<Lesson>(lessonKey(lesson.slug), (current) =>
        current ? { ...current, progress: { status: result.status, completed_at: result.completed_at } } : current,
      );
      queryClient.setQueryData(catalogKeys.progress(lesson.course.slug), result.course);
      // Topic status on the roadmap and the knowledge map follows lesson progress.
      void queryClient.invalidateQueries({ queryKey: ["roadmap"] });
    },
  });
}
