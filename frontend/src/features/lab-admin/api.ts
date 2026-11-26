import type { LabAction, LabActionResult, LabDetail } from "@/features/labs/types";
import { api, request } from "@/lib/api/client";
import type { AdminLab, CheckReport, LabDefinition, LabRevision, LabsIndex } from "./types";

const base = "/api/v1/admin/content/labs";

export const fetchAdminLabs = (signal?: AbortSignal) => request<LabsIndex>("GET", base, { signal });

export const fetchAdminLab = (id: number, signal?: AbortSignal) => api.get<AdminLab>(`${base}/${id}`, { signal });

export const createLab = (body: { slug: string; engine: string; title: string; domain: string }) =>
  api.post<AdminLab>(base, body);

export const saveLabDraft = (id: number, definition: LabDefinition, lockVersion: number) =>
  api.put<AdminLab>(`${base}/${id}/draft`, { ...definition, lock_version: lockVersion });

export const discardLabDraft = (id: number) => api.delete<AdminLab>(`${base}/${id}/draft`);

/** Replays every solution and decoy; with a definition it checks that, otherwise the working copy. */
export const checkLab = (id: number, definition?: LabDefinition) =>
  api.post<CheckReport>(`${base}/${id}/check`, definition);

export const publishLab = (id: number, body: { lock_version: number; note?: string; confirm_restart?: boolean }) =>
  api.post<AdminLab>(`${base}/${id}/publish`, body);

export const archiveLab = (id: number, archived: boolean) =>
  archived ? api.post<AdminLab>(`${base}/${id}/archive`) : api.delete<AdminLab>(`${base}/${id}/archive`);

export const orderLabs = (ids: number[]) => api.put<{ ids: number[] }>(`${base}/order`, { ids });

export const fetchLabRevisions = (id: number, signal?: AbortSignal) =>
  api.get<LabRevision[]>(`${base}/${id}/revisions`, { signal });

export const restoreLabRevision = (id: number, version: number) =>
  api.post<AdminLab>(`${base}/${id}/revisions/${version}/restore`);

/** The preview plays the saved working copy; nothing is written and no event fires (D79). */
export const openPreview = (id: number) => api.post<LabDetail>(`${base}/${id}/preview`);

export const actInPreview = (id: number, version: number, action: LabAction) =>
  api.post<LabActionResult>(`${base}/${id}/preview/actions`, { version, action });

export const resetPreview = (id: number) => api.post<LabDetail>(`${base}/${id}/preview/reset`);
