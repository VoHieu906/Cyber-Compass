import { api, request } from "@/lib/api/client";
import type { CatalogQuery, CourseProgress, CourseSummary, Domain, Paginated } from "./types";

export const PER_PAGE = 6;

/** CatalogQuery -> API query string (filter[...] keys, defaults omitted). */
export function catalogParams(query: CatalogQuery): Record<string, string | number | undefined> {
  return {
    q: query.q.trim() || undefined,
    "filter[domain]": query.domain || undefined,
    "filter[difficulty]": query.difficulty || undefined,
    "filter[favorited]": query.favorited ? 1 : undefined,
    sort: query.sort === "newest" ? undefined : query.sort,
    page: query.page > 1 ? query.page : undefined,
    per_page: PER_PAGE,
  };
}

export const fetchCourses = (query: CatalogQuery, signal?: AbortSignal) =>
  request<Paginated<CourseSummary>>("GET", "/api/v1/courses", { query: catalogParams(query), signal });

export const fetchDomains = (signal?: AbortSignal) => api.get<Domain[]>("/api/v1/domains", { signal });

export const fetchCourseProgress = (slug: string, signal?: AbortSignal) =>
  api.get<CourseProgress>(`/api/v1/courses/${encodeURIComponent(slug)}/progress`, { signal });

export const enroll = (slug: string) => api.post<CourseProgress>(`/api/v1/courses/${encodeURIComponent(slug)}/enroll`);

export const setFavorite = (slug: string, favorited: boolean) =>
  favorited
    ? api.post<{ favorited: boolean }>(`/api/v1/courses/${encodeURIComponent(slug)}/favorite`)
    : api.delete<{ favorited: boolean }>(`/api/v1/courses/${encodeURIComponent(slug)}/favorite`);
