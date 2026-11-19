import { SORTS, type CatalogQuery, type Difficulty, type Sort } from "./types";

const DIFFICULTIES: readonly Difficulty[] = ["beginner", "intermediate", "advanced"];

export const DEFAULT_QUERY: CatalogQuery = { q: "", domain: "", difficulty: "", favorited: false, sort: "newest", page: 1 };

type Params = { get(name: string): string | null };

/** URL search params -> catalog query. Unknown or malformed values fall back to defaults. */
export function parseCatalogQuery(params: Params): CatalogQuery {
  const difficulty = params.get("difficulty") ?? "";
  const sort = params.get("sort") ?? "";
  const page = Number.parseInt(params.get("page") ?? "1", 10);
  return {
    q: (params.get("q") ?? "").slice(0, 100),
    domain: params.get("domain") ?? "",
    difficulty: (DIFFICULTIES as readonly string[]).includes(difficulty) ? (difficulty as Difficulty) : "",
    favorited: params.get("favorites") === "1",
    sort: (SORTS as readonly string[]).includes(sort) ? (sort as Sort) : "newest",
    page: Number.isFinite(page) && page > 0 ? page : 1,
  };
}

/** Catalog query -> URL search string, omitting defaults so URLs stay short. */
export function catalogQueryToSearch(query: CatalogQuery): string {
  const params = new URLSearchParams();
  if (query.q.trim()) params.set("q", query.q.trim());
  if (query.domain) params.set("domain", query.domain);
  if (query.difficulty) params.set("difficulty", query.difficulty);
  if (query.favorited) params.set("favorites", "1");
  if (query.sort !== "newest") params.set("sort", query.sort);
  if (query.page > 1) params.set("page", String(query.page));
  const search = params.toString();
  return search ? `?${search}` : "";
}

export function hasActiveFilters(query: CatalogQuery): boolean {
  return Boolean(query.q.trim() || query.domain || query.difficulty || query.favorited);
}
