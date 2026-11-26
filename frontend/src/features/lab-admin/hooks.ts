"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import type { LabActionResult, LabDetail } from "@/features/labs/types";
import {
  actInPreview,
  archiveLab,
  checkLab,
  createLab,
  discardLabDraft,
  fetchAdminLab,
  fetchAdminLabs,
  fetchLabRevisions,
  openPreview,
  orderLabs,
  publishLab,
  resetPreview,
  restoreLabRevision,
  saveLabDraft,
} from "./api";
import type { AdminLab, LabDefinition } from "./types";

export const labAdminKeys = {
  all: ["admin", "labs"] as const,
  list: ["admin", "labs", "list"] as const,
  lab: (id: number) => ["admin", "labs", "lab", id] as const,
  revisions: (id: number) => ["admin", "labs", "revisions", id] as const,
  preview: (id: number, lockVersion: number) => ["admin", "labs", "preview", id, lockVersion] as const,
};

export function useAdminLabs() {
  return useQuery({ queryKey: labAdminKeys.list, queryFn: ({ signal }) => fetchAdminLabs(signal) });
}

/**
 * The lab in the editor. Never refetched behind the editor's back: the form holds unsaved work,
 * and `lock_version` decides what the server accepts.
 */
export function useAdminLab(id: number) {
  return useQuery({
    queryKey: labAdminKeys.lab(id),
    queryFn: ({ signal }) => fetchAdminLab(id, signal),
    refetchOnWindowFocus: false,
    staleTime: Infinity,
  });
}

/** Puts a lab the server just returned into the cache and refreshes the list. */
function useStoreLab() {
  const queryClient = useQueryClient();
  return (lab: AdminLab) => {
    queryClient.setQueryData(labAdminKeys.lab(lab.id), lab);
    void queryClient.invalidateQueries({ queryKey: labAdminKeys.list });
  };
}

export function useCreateLab() {
  const store = useStoreLab();
  return useMutation({ mutationFn: (body: Parameters<typeof createLab>[0]) => createLab(body), onSuccess: store });
}

export function useSaveLabDraft(id: number) {
  const store = useStoreLab();
  return useMutation({
    mutationFn: ({ definition, lockVersion }: { definition: LabDefinition; lockVersion: number }) =>
      saveLabDraft(id, definition, lockVersion),
    onSuccess: store,
  });
}

export function useDiscardLabDraft(id: number) {
  const store = useStoreLab();
  return useMutation({ mutationFn: () => discardLabDraft(id), onSuccess: store });
}

/** Replays every solution and decoy. Used by "Run solutions"; the report is the caller's to show. */
export function useCheckLab(id: number) {
  return useMutation({ mutationFn: (definition?: LabDefinition) => checkLab(id, definition) });
}

export function usePublishLab(id: number) {
  const store = useStoreLab();
  return useMutation({
    mutationFn: (body: { lock_version: number; note?: string; confirm_restart?: boolean }) => publishLab(id, body),
    onSuccess: store,
  });
}

export function useArchiveLab() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, archived }: { id: number; archived: boolean }) => archiveLab(id, archived),
    onSuccess: (lab) => {
      queryClient.setQueryData(labAdminKeys.lab(lab.id), lab);
      void queryClient.invalidateQueries({ queryKey: labAdminKeys.list });
    },
  });
}

export function useOrderLabs() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (ids: number[]) => orderLabs(ids),
    onSettled: () => void queryClient.invalidateQueries({ queryKey: labAdminKeys.list }),
  });
}

export function useLabRevisions(id: number) {
  return useQuery({ queryKey: labAdminKeys.revisions(id), queryFn: ({ signal }) => fetchLabRevisions(id, signal) });
}

export function useRestoreLabRevision(id: number) {
  const store = useStoreLab();
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (version: number) => restoreLabRevision(id, version),
    onSuccess: (lab) => {
      store(lab);
      void queryClient.invalidateQueries({ queryKey: labAdminKeys.revisions(id) });
    },
  });
}

/**
 * The admin preview (D79): the working copy played on the server, state kept in the cache for two
 * hours. Opening it again after a save restarts it when the draft changed. The result is never kept
 * past the page (`gcTime` 0): it holds the answers' effects, and a stale copy must not be shown.
 */
export function usePreview(id: number, lockVersion: number) {
  return useQuery({
    queryKey: labAdminKeys.preview(id, lockVersion),
    queryFn: () => openPreview(id),
    staleTime: Infinity,
    gcTime: 0,
    refetchOnWindowFocus: false,
    retry: false,
  });
}

export function usePreviewActions(id: number, lockVersion: number) {
  const queryClient = useQueryClient();
  const key = labAdminKeys.preview(id, lockVersion);
  const apply = (result: LabActionResult) =>
    queryClient.setQueryData<LabDetail>(key, (current) =>
      current
        ? {
            ...current,
            attempt: result.attempt,
            checkpoints: result.checkpoints,
            progress: {
              ...current.progress,
              passed: result.checkpoints.filter((c) => c.passed && !c.starts_true).length,
              completed: current.progress.completed || result.attempt.completed_at !== null,
            },
          }
        : current,
    );
  const act = useMutation({
    mutationFn: ({ version, action }: { version: number; action: Parameters<typeof actInPreview>[2] }) =>
      actInPreview(id, version, action),
    onSuccess: apply,
  });
  const reset = useMutation({
    mutationFn: () => resetPreview(id),
    onSuccess: (detail) => queryClient.setQueryData(key, detail),
  });
  return { act, reset };
}
