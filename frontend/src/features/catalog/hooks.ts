"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useCurrentUser } from "@/features/auth/hooks";
import { enroll, fetchCourseProgress, fetchCourses, fetchDomains, setFavorite } from "./api";
import type { CatalogQuery, CourseProgress, CourseSummary, Paginated } from "./types";

export const catalogKeys = {
  all: ["courses"] as const,
  list: (query: CatalogQuery, userId: number | null) => ["courses", "list", userId, query] as const,
  progress: (slug: string) => ["courses", "progress", slug] as const,
  domains: ["domains"] as const,
};

export function useCourses(query: CatalogQuery) {
  const { data: user, isPending } = useCurrentUser();
  return useQuery({
    // The user id is part of the key: favorites differ per viewer.
    queryKey: catalogKeys.list(query, user?.id ?? null),
    queryFn: ({ signal }) => fetchCourses(query, signal),
    placeholderData: keepPreviousData,
    enabled: !isPending,
  });
}

export function useDomains() {
  return useQuery({ queryKey: catalogKeys.domains, queryFn: ({ signal }) => fetchDomains(signal), staleTime: 10 * 60_000 });
}

export function useCourseProgress(slug: string) {
  const { data: user } = useCurrentUser();
  return useQuery({
    queryKey: catalogKeys.progress(slug),
    queryFn: ({ signal }) => fetchCourseProgress(slug, signal),
    enabled: Boolean(user),
  });
}

export function useEnroll(slug: string) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => enroll(slug),
    onSuccess: (progress) => queryClient.setQueryData(catalogKeys.progress(slug), progress),
  });
}

/**
 * Favorite toggle with an optimistic update: the heart changes at once, and is
 * put back if the server refuses.
 */
export function useToggleFavorite() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ slug, favorited }: { slug: string; favorited: boolean }) => setFavorite(slug, favorited),
    onMutate: async ({ slug, favorited }) => {
      await queryClient.cancelQueries({ queryKey: catalogKeys.all });
      const lists = queryClient.getQueriesData<Paginated<CourseSummary>>({ queryKey: ["courses", "list"] });
      const progress = queryClient.getQueryData<CourseProgress>(catalogKeys.progress(slug));

      for (const [key, page] of lists) {
        if (!page) continue;
        queryClient.setQueryData(key, {
          ...page,
          data: page.data.map((c) => (c.slug === slug ? { ...c, is_favorited: favorited } : c)),
        });
      }
      if (progress) queryClient.setQueryData(catalogKeys.progress(slug), { ...progress, favorited });

      return { lists, progress };
    },
    onError: (_error, { slug }, context) => {
      context?.lists.forEach(([key, page]) => queryClient.setQueryData(key, page));
      if (context?.progress) queryClient.setQueryData(catalogKeys.progress(slug), context.progress);
    },
    // A "favorites only" list must drop or gain the course, so refetch lists afterwards.
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["courses", "list"] }),
  });
}
