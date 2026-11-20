import { USER_SORTS, type AuditQuery, type UserQuery } from "./types";

type Params = { get(name: string): string | null };

export const DEFAULT_USER_QUERY: UserQuery = { q: "", role: "", status: "", sort: "-created_at", page: 1 };

function positiveInt(value: string | null): number | null {
  const n = Number.parseInt(value ?? "", 10);
  return Number.isFinite(n) && n > 0 ? n : null;
}

/** URL -> user list query. Unknown values fall back to defaults, so a bad link still renders. */
export function parseUserQuery(params: Params): UserQuery {
  const role = params.get("role");
  const status = params.get("status");
  const sort = params.get("sort") ?? "";
  return {
    q: (params.get("q") ?? "").slice(0, 100),
    role: role === "learner" || role === "admin" ? role : "",
    status: status === "active" || status === "suspended" ? status : "",
    sort: (USER_SORTS as readonly string[]).includes(sort) ? (sort as UserQuery["sort"]) : "-created_at",
    page: positiveInt(params.get("page")) ?? 1,
  };
}

/** User list query -> URL search string, omitting defaults. */
export function userQueryToSearch(query: UserQuery): string {
  const params = new URLSearchParams();
  if (query.q.trim()) params.set("q", query.q.trim());
  if (query.role) params.set("role", query.role);
  if (query.status) params.set("status", query.status);
  if (query.sort !== DEFAULT_USER_QUERY.sort) params.set("sort", query.sort);
  if (query.page > 1) params.set("page", String(query.page));
  const search = params.toString();
  return search ? `?${search}` : "";
}

/** User list query -> API parameters (filter[...] style, docs/API_SPECIFICATION.md). */
export function userQueryToApi(query: UserQuery): Record<string, string | number | undefined> {
  return {
    q: query.q.trim() || undefined,
    "filter[role]": query.role || undefined,
    "filter[status]": query.status || undefined,
    sort: query.sort,
    page: query.page,
  };
}

export function parseAuditQuery(params: Params): AuditQuery {
  const type = params.get("subject_type");
  return {
    actor: positiveInt(params.get("actor")),
    subjectType: type === "user" || type === "lesson" || type === "chapter" ? type : "",
    subjectId: positiveInt(params.get("subject_id")),
    page: positiveInt(params.get("page")) ?? 1,
  };
}

export function auditQueryToSearch(query: AuditQuery): string {
  const params = new URLSearchParams();
  if (query.actor) params.set("actor", String(query.actor));
  if (query.subjectType) params.set("subject_type", query.subjectType);
  if (query.subjectId) params.set("subject_id", String(query.subjectId));
  if (query.page > 1) params.set("page", String(query.page));
  const search = params.toString();
  return search ? `?${search}` : "";
}

export function auditQueryToApi(query: AuditQuery): Record<string, string | number | undefined> {
  return {
    "filter[actor]": query.actor ?? undefined,
    "filter[subject_type]": query.subjectType || undefined,
    "filter[subject_id]": query.subjectId ?? undefined,
    page: query.page,
  };
}
