import { api } from "@/lib/api/client";
import type { Exercise, ExerciseResult, SubmittedAnswer } from "./types";

const path = (slug: string) => `/api/v1/lessons/${encodeURIComponent(slug)}/exercise`;

/** The lesson's exercise without its answers. 404 when the lesson has none. */
export const fetchExercise = (slug: string, signal?: AbortSignal) => api.get<Exercise>(path(slug), { signal });

/** Scores an attempt; a passing one completes the lesson. */
export const submitExercise = (slug: string, answers: SubmittedAnswer[], timeSpentSeconds: number) =>
  api.post<ExerciseResult>(`${path(slug)}/attempts`, { answers, time_spent_seconds: timeSpentSeconds });
