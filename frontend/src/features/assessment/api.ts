import { api, request } from "@/lib/api/client";
import type { ActiveAssessment, Attempt, AttemptResult, AttemptSummary, SavedAnswer } from "./types";

const attempt = (id: number) => `/api/v1/assessment-attempts/${id}`;

export const fetchActiveAssessment = (signal?: AbortSignal) => api.get<ActiveAssessment>("/api/v1/assessments/active", { signal });

/** Starts a new attempt or resumes the open one. */
export const startAttempt = (slug: string) => api.post<Attempt>(`/api/v1/assessments/${encodeURIComponent(slug)}/attempts`);

export const fetchAttempt = (id: number, signal?: AbortSignal) => api.get<Attempt>(attempt(id), { signal });

export const saveAnswer = (id: number, questionId: number, optionId: number | null) =>
  api.put<SavedAnswer>(`${attempt(id)}/answers/${questionId}`, { option_id: optionId });

export const submitAttempt = (id: number) => api.post<AttemptResult>(`${attempt(id)}/submit`);

export const fetchResult = (id: number, signal?: AbortSignal) => api.get<AttemptResult>(`${attempt(id)}/result`, { signal });

/** Submitted attempts, newest first (paginated; the first page is enough for the history list). */
export const fetchAttemptHistory = (signal?: AbortSignal) =>
  request<{ data: AttemptSummary[]; meta: { total: number } }>("GET", "/api/v1/assessment-attempts", { signal, query: { per_page: 20 } });
