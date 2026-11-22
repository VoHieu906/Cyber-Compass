"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useCallback, useRef, useState } from "react";
import { ApiError } from "@/lib/api/client";
import { fetchActiveAssessment, fetchAttempt, fetchAttemptHistory, fetchResult, saveAnswer, startAttempt, submitAttempt } from "./api";
import type { Selections } from "./progress";
import type { Attempt, AttemptResult } from "./types";

export const assessmentKeys = {
  all: ["assessment"] as const,
  active: ["assessment", "active"] as const,
  history: ["assessment", "history"] as const,
  attempt: (id: number) => ["assessment", "attempt", id] as const,
  result: (id: number) => ["assessment", "result", id] as const,
};

export function useActiveAssessment(enabled = true) {
  return useQuery({ queryKey: assessmentKeys.active, queryFn: ({ signal }) => fetchActiveAssessment(signal), enabled });
}

export function useAttemptHistory() {
  return useQuery({ queryKey: assessmentKeys.history, queryFn: ({ signal }) => fetchAttemptHistory(signal) });
}

export function useAttempt(id: number) {
  // Answers change only through this page, so a refetch on focus would only reset the view.
  return useQuery({ queryKey: assessmentKeys.attempt(id), queryFn: ({ signal }) => fetchAttempt(id, signal), refetchOnWindowFocus: false });
}

export function useAttemptResult(id: number) {
  return useQuery({ queryKey: assessmentKeys.result(id), queryFn: ({ signal }) => fetchResult(id, signal), retry: false });
}

export function useStartAttempt() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: startAttempt,
    onSuccess: (attempt) => {
      queryClient.setQueryData(assessmentKeys.attempt(attempt.id), attempt);
      void queryClient.invalidateQueries({ queryKey: assessmentKeys.active });
    },
  });
}

export function useSubmitAttempt(id: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => submitAttempt(id),
    onSuccess: (result: AttemptResult) => {
      queryClient.setQueryData(assessmentKeys.result(id), result);
      queryClient.setQueryData<Attempt>(assessmentKeys.attempt(id), (current) =>
        current ? { ...current, status: "submitted", submitted_at: result.submitted_at } : current,
      );
      void queryClient.invalidateQueries({ queryKey: assessmentKeys.active });
      void queryClient.invalidateQueries({ queryKey: assessmentKeys.history });
    },
  });
}

/**
 * Autosaves answers. Each question has its own queue, and every request sends the latest
 * choice at the moment it goes out, so quick changes are saved in order and the last one wins.
 * `onSubmitted` runs when the server says the attempt was already submitted (another tab).
 */
export function useAnswerSaver(attemptId: number, onSubmitted: () => void) {
  const [local, setLocal] = useState<Selections>({});
  const [pending, setPending] = useState(0);
  const [failed, setFailed] = useState<number[]>([]);
  const queues = useRef(new Map<number, Promise<void>>());
  const latest = useRef(new Map<number, number | null>());

  const send = useCallback(
    (questionId: number) => {
      setPending((n) => n + 1);
      const previous = queues.current.get(questionId) ?? Promise.resolve();
      const next = previous
        .then(async () => {
          try {
            await saveAnswer(attemptId, questionId, latest.current.get(questionId) ?? null);
            setFailed((ids) => ids.filter((id) => id !== questionId));
          } catch (error) {
            if (error instanceof ApiError && error.status === 409) onSubmitted();
            else setFailed((ids) => (ids.includes(questionId) ? ids : [...ids, questionId]));
          }
        })
        .finally(() => setPending((n) => n - 1));
      queues.current.set(questionId, next);
    },
    [attemptId, onSubmitted],
  );

  const choose = useCallback(
    (questionId: number, optionId: number | null) => {
      latest.current.set(questionId, optionId);
      setLocal((current) => ({ ...current, [questionId]: optionId }));
      send(questionId);
    },
    [send],
  );

  const retry = useCallback(() => failed.forEach(send), [failed, send]);

  return { local, choose, saving: pending > 0, failed, retry };
}
