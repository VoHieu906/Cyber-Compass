import { describe, expect, it } from "vitest";
import {
  auditQueryToApi,
  auditQueryToSearch,
  DEFAULT_USER_QUERY,
  parseAuditQuery,
  parseUserQuery,
  userQueryToApi,
  userQueryToSearch,
} from "./query-state";

const params = (search: string) => new URLSearchParams(search);

describe("user list query", () => {
  it("falls back to defaults for missing or unknown values", () => {
    expect(parseUserQuery(params(""))).toEqual(DEFAULT_USER_QUERY);
    expect(parseUserQuery(params("role=root&status=gone&sort=password&page=-2"))).toEqual(DEFAULT_USER_QUERY);
  });

  it("round-trips through the URL, omitting defaults", () => {
    const query = parseUserQuery(params("q=lee&role=admin&status=suspended&sort=name&page=3"));
    expect(query).toEqual({ q: "lee", role: "admin", status: "suspended", sort: "name", page: 3 });
    expect(userQueryToSearch(query)).toBe("?q=lee&role=admin&status=suspended&sort=name&page=3");
    expect(userQueryToSearch(DEFAULT_USER_QUERY)).toBe("");
  });

  it("maps to the API's filter[] parameters", () => {
    expect(userQueryToApi({ ...DEFAULT_USER_QUERY, q: "  ada ", role: "admin" })).toEqual({
      q: "ada",
      "filter[role]": "admin",
      "filter[status]": undefined,
      sort: "-created_at",
      page: 1,
    });
  });
});

describe("audit log query", () => {
  it("keeps only valid ids and subject types", () => {
    expect(parseAuditQuery(params("actor=7&subject_type=user&subject_id=x&page=2"))).toEqual({
      actor: 7,
      subjectType: "user",
      subjectId: null,
      page: 2,
    });
    expect(parseAuditQuery(params("subject_type=planet")).subjectType).toBe("");
  });

  it("maps to the URL and the API", () => {
    const query = { actor: null, subjectType: "user" as const, subjectId: 4, page: 1 };
    expect(auditQueryToSearch(query)).toBe("?subject_type=user&subject_id=4");
    expect(auditQueryToApi(query)).toEqual({
      "filter[actor]": undefined,
      "filter[subject_type]": "user",
      "filter[subject_id]": 4,
      page: 1,
    });
  });
});
