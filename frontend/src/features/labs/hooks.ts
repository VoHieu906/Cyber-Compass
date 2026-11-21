"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ApiError } from "@/lib/api/client";
import { actInLab, fetchLab, fetchLabs, resetLab, startLab } from "./api";
import type { LabAction, LabDetail } from "./types";

export const labKeys = {
  all: ["labs"] as const,
  list: ["labs", "list"] as const,
  detail: (slug: string) => ["labs", "detail", slug] as const,
};

export function useLabs() {
  return useQuery({ queryKey: labKeys.list, queryFn: ({ signal }) => fetchLabs(signal) });
}

export function useLab(slug: string) {
  return useQuery({ queryKey: labKeys.detail(slug), queryFn: ({ signal }) => fetchLab(slug, signal), retry: false });
}

export function useStartLab(slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => startLab(slug),
    onSuccess: (detail) => {
      queryClient.setQueryData(labKeys.detail(slug), detail);
      void queryClient.invalidateQueries({ queryKey: labKeys.list });
    },
  });
}

/**
 * Sends one action with the version this page last saw. The result replaces the attempt and
 * checkpoints in the cache. A 409 means another tab moved on: the lab is reloaded so this page
 * continues from the latest state (ARCHITECTURE §7.1).
 */
export function useLabAction(slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ attemptId, version, action }: { attemptId: number; version: number; action: LabAction }) => actInLab(attemptId, version, action),
    onSuccess: (result) => {
      queryClient.setQueryData<LabDetail>(labKeys.detail(slug), (current) =>
        current
          ? {
              ...current,
              attempt: result.attempt,
              checkpoints: result.checkpoints,
              progress: {
                ...current.progress,
                passed: result.checkpoints.filter((c) => c.passed).length,
                completed: current.progress.completed || result.attempt.completed_at !== null,
              },
            }
          : current,
      );
      if (result.newly_passed.length) void queryClient.invalidateQueries({ queryKey: labKeys.list });
    },
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) void queryClient.invalidateQueries({ queryKey: labKeys.detail(slug) });
    },
  });
}

export function useResetLab(slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ attemptId, version }: { attemptId: number; version: number }) => resetLab(attemptId, version),
    onSuccess: (detail) => {
      queryClient.setQueryData(labKeys.detail(slug), detail);
      void queryClient.invalidateQueries({ queryKey: labKeys.list });
    },
    onError: (error) => {
      if (error instanceof ApiError && error.status === 409) void queryClient.invalidateQueries({ queryKey: labKeys.detail(slug) });
    },
  });
}
