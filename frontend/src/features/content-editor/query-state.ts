import { LESSON_SORTS, type LessonQuery, type LessonStatus } from "./types";

type Params = { get(name: string): string | null };

export const DEFAULT_LESSON_QUERY: LessonQuery = {
  q: "",
  course: null,
  status: "",
  drafts: false,
  sort: "-updated_at",
  page: 1,
};

const STATUSES: readonly LessonStatus[] = ["draft", "published", "archived"];

function positiveInt(value: string | null): number | null {
  const n = Number.parseInt(value ?? "", 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

/** URL -> lesson list query. Unknown values fall back to defaults. */
export function parseLessonQuery(params: Params): LessonQuery {
  const status = params.get("status") ?? "";
  const sort = params.get("sort") ?? "";
  return {
    q: (params.get("q") ?? "").slice(0, 100),
    course: positiveInt(params.get("course")),
    status: (STATUSES as readonly string[]).includes(status) ? (status as LessonStatus) : "",
    drafts: params.get("drafts") === "1",
    sort: (LESSON_SORTS as readonly string[]).includes(sort) ? (sort as LessonQuery["sort"]) : "-updated_at",
    page: positiveInt(params.get("page")) ?? 1,
  };
}

export function lessonQueryToSearch(query: LessonQuery): string {
  const params = new URLSearchParams();
  if (query.q.trim()) params.set("q", query.q.trim());
  if (query.course) params.set("course", String(query.course));
  if (query.status) params.set("status", query.status);
  if (query.drafts) params.set("drafts", "1");
  if (query.sort !== DEFAULT_LESSON_QUERY.sort) params.set("sort", query.sort);
  if (query.page > 1) params.set("page", String(query.page));
  const search = params.toString();
  return search ? `?${search}` : "";
}

export function lessonQueryToApi(query: LessonQuery): Record<string, string | number | undefined> {
  return {
    q: query.q.trim() || undefined,
    "filter[course]": query.course ?? undefined,
    "filter[status]": query.status || undefined,
    "filter[has_draft]": query.drafts ? 1 : undefined,
    sort: query.sort,
    page: query.page,
  };
}

/** A URL slug from a title: lowercase ASCII words joined by single hyphens (Vietnamese accents dropped). */
export function slugify(title: string): string {
  return title
    .normalize("NFD")
    .replace(/[̀-ͯ]/g, "")
    .replace(/đ/gi, "d")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 120)
    .replace(/-+$/, "");
}
