"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useCallback, useEffect, useState } from "react";
import { lessonKey } from "@/features/learning/hooks";
import { fetchExercise, submitExercise } from "./api";
import type { Draft } from "./answers";
import type { Response, SubmittedAnswer } from "./types";

export const exerciseKey = (slug: string) => ["exercises", slug] as const;

export function useExercise(slug: string) {
  return useQuery({ queryKey: exerciseKey(slug), queryFn: ({ signal }) => fetchExercise(slug, signal), retry: false });
}

/** Submits an attempt. A pass completes the lesson, so everything that shows lesson status refreshes. */
export function useSubmitExercise(slug: string, courseSlug: string | undefined) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ answers, seconds }: { answers: SubmittedAnswer[]; seconds: number }) => submitExercise(slug, answers, seconds),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: exerciseKey(slug) });
      void queryClient.invalidateQueries({ queryKey: lessonKey(slug) });
      if (courseSlug) void queryClient.invalidateQueries({ queryKey: ["courses", "progress", courseSlug] });
      void queryClient.invalidateQueries({ queryKey: ["roadmap"] });
    },
  });
}

const storageKey = (slug: string) => `exercise-draft:${slug}`;

function readDraft(slug: string): Draft {
  try {
    const raw = window.sessionStorage.getItem(storageKey(slug));
    return raw ? (JSON.parse(raw) as Draft) : {};
  } catch {
    return {};
  }
}

/**
 * The answers in progress, kept in session storage so a reload or a detour to the lesson does
 * not lose them (ARCHITECTURE §5: the exercise draft is transient client state). Nothing is
 * sent until the attempt is submitted. Use it in a component that mounts on the client.
 */
export function useExerciseDraft(slug: string) {
  // Mounted only on the client, after the exercise has loaded, so session storage is available.
  const [draft, setDraft] = useState<Draft>(() => (typeof window === "undefined" ? {} : readDraft(slug)));

  useEffect(() => {
    try {
      window.sessionStorage.setItem(storageKey(slug), JSON.stringify(draft));
    } catch {
      // Storage may be full or blocked; the draft still lives in memory.
    }
  }, [slug, draft]);

  const answer = useCallback((questionId: number, response: Response) => setDraft((d) => ({ ...d, [questionId]: response })), []);
  const clear = useCallback(() => {
    setDraft({});
    try {
      window.sessionStorage.removeItem(storageKey(slug));
    } catch {
      // Nothing to remove.
    }
  }, [slug]);

  return { draft, answer, clear };
}
