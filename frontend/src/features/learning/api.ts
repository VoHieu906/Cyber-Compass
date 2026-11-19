import { api } from "@/lib/api/client";
import type { CompleteLessonResult, Lesson } from "./types";

const path = (slug: string) => `/api/v1/lessons/${encodeURIComponent(slug)}`;

export const fetchLesson = (slug: string, signal?: AbortSignal) => api.get<Lesson>(path(slug), { signal });

export const startLesson = (slug: string) => api.post<{ status: string }>(`${path(slug)}/start`);

export const completeLesson = (slug: string, timeSpentSeconds: number) =>
  api.post<CompleteLessonResult>(`${path(slug)}/complete`, { time_spent_seconds: timeSpentSeconds });
