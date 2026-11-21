"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { currentUserKey } from "@/features/auth/hooks";
import type { User } from "@/features/auth/types";
import { updatePreferences } from "@/features/profile/api";
import { fetchActivity, fetchAssessments, fetchCourses, fetchNext, fetchRecent, fetchSkills, fetchSummary } from "./api";
import type { Range } from "./types";

export const progressKeys = {
  all: ["progress"] as const,
  summary: ["progress", "summary"] as const,
  activity: (range: Range) => ["progress", "activity", range] as const,
  skills: ["progress", "skills"] as const,
  recent: ["progress", "recent"] as const,
  next: ["progress", "next"] as const,
  courses: ["progress", "courses"] as const,
  assessments: ["progress", "assessments"] as const,
};

/** Progress changes whenever the learner studies elsewhere, so the dashboard refetches on every visit. */
const fresh = { staleTime: 0 } as const;

export const useSummary = () => useQuery({ queryKey: progressKeys.summary, queryFn: ({ signal }) => fetchSummary(signal), ...fresh });

/** The previous range stays on screen while a new one loads, so the charts keep their frame. */
export const useWeeklyActivity = (range: Range) =>
  useQuery({ queryKey: progressKeys.activity(range), queryFn: ({ signal }) => fetchActivity(range, signal), placeholderData: keepPreviousData, ...fresh });

export const useSkills = () => useQuery({ queryKey: progressKeys.skills, queryFn: ({ signal }) => fetchSkills(signal), ...fresh });
export const useRecentActivity = () => useQuery({ queryKey: progressKeys.recent, queryFn: ({ signal }) => fetchRecent(signal), ...fresh });
export const useNextLessons = () => useQuery({ queryKey: progressKeys.next, queryFn: ({ signal }) => fetchNext(signal), ...fresh });
export const useCourseCompletion = () => useQuery({ queryKey: progressKeys.courses, queryFn: ({ signal }) => fetchCourses(signal), ...fresh });
export const useAssessmentHistory = () => useQuery({ queryKey: progressKeys.assessments, queryFn: ({ signal }) => fetchAssessments(signal), ...fresh });

/**
 * Saves the browser's timezone, so "today" for streaks and reminders is the learner's own (D33).
 * The new user replaces /me, and progress refetches with local dates.
 */
export function useSaveTimezone() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (timezone: string) => updatePreferences({ timezone }),
    onSuccess: (user: User) => {
      queryClient.setQueryData(currentUserKey, user);
      void queryClient.invalidateQueries({ queryKey: progressKeys.all });
    },
  });
}
