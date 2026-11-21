import { api } from "@/lib/api/client";
import type { LabAction, LabActionResult, LabDetail, LabSummary } from "./types";

export const fetchLabs = (signal?: AbortSignal) => api.get<LabSummary[]>("/api/v1/labs", { signal });

export const fetchLab = (slug: string, signal?: AbortSignal) => api.get<LabDetail>(`/api/v1/labs/${encodeURIComponent(slug)}`, { signal });

/** Starts the lab, or returns my existing attempt. */
export const startLab = (slug: string) => api.post<LabDetail>(`/api/v1/labs/${encodeURIComponent(slug)}/attempts`);

/** One action, run by the server engine; `version` is the attempt version this page last saw (409 if stale). */
export const actInLab = (attemptId: number, version: number, action: LabAction) =>
  api.post<LabActionResult>(`/api/v1/lab-attempts/${attemptId}/actions`, { version, action });

export const resetLab = (attemptId: number, version: number) => api.post<LabDetail>(`/api/v1/lab-attempts/${attemptId}/reset`, { version });
