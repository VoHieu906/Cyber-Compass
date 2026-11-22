import { api } from "@/lib/api/client";

export type SearchHit = { slug: string; title: string; summary: string | null };

/** GET /search (D36): at most five of each, open to guests. */
export type SearchResults = {
  query: string;
  courses: (SearchHit & { domain: string | null })[];
  lessons: (SearchHit & { course: string })[];
  careers: SearchHit[];
  posts: SearchHit[];
};

/** The shortest query the API accepts. */
export const MIN_QUERY = 2;

export const search = (q: string, signal?: AbortSignal) =>
  api.get<SearchResults>("/api/v1/search", { signal, query: { q } });
