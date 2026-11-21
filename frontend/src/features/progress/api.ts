import { api } from "@/lib/api/client";
import type { Activity, AssessmentHistoryItem, CourseCompletion, NextLessons, ProgressSummary, Range, SkillDomain, WeeklyActivity } from "./types";

const get = <T,>(path: string, signal?: AbortSignal, query?: Record<string, string>) => api.get<T>(`/api/v1/progress/${path}`, { signal, query });

export const fetchSummary = (signal?: AbortSignal) => get<ProgressSummary>("summary", signal);
export const fetchActivity = (range: Range, signal?: AbortSignal) => get<WeeklyActivity>("activity", signal, { range });
export const fetchSkills = (signal?: AbortSignal) => get<SkillDomain[]>("skills", signal);
export const fetchRecent = (signal?: AbortSignal) => get<Activity[]>("recent", signal);
export const fetchNext = (signal?: AbortSignal) => get<NextLessons>("next", signal);
export const fetchCourses = (signal?: AbortSignal) => get<CourseCompletion[]>("courses", signal);
export const fetchAssessments = (signal?: AbortSignal) => get<AssessmentHistoryItem[]>("assessments", signal);
