import { describe, expect, it } from "vitest";
import { catalogParams } from "./api";
import { catalogQueryToSearch, DEFAULT_QUERY, hasActiveFilters, parseCatalogQuery } from "./query-state";

const params = (search: string) => new URLSearchParams(search);

describe("catalog query state", () => {
  it("parses a full URL", () => {
    expect(parseCatalogQuery(params("q=dns&domain=networking&difficulty=beginner&favorites=1&sort=-duration&page=2"))).toEqual({
      q: "dns",
      domain: "networking",
      difficulty: "beginner",
      favorited: true,
      sort: "-duration",
      page: 2,
    });
  });

  it("falls back to defaults for unknown or malformed values", () => {
    expect(parseCatalogQuery(params("difficulty=expert&sort=price&page=-3&favorites=yes"))).toEqual(DEFAULT_QUERY);
  });

  it("round-trips through the URL and omits defaults", () => {
    const query = { ...DEFAULT_QUERY, q: " xss ", domain: "web-security", page: 3 };
    const search = catalogQueryToSearch(query);

    expect(search).toBe("?q=xss&domain=web-security&page=3");
    expect(parseCatalogQuery(params(search.slice(1)))).toEqual({ ...query, q: "xss" });
    expect(catalogQueryToSearch(DEFAULT_QUERY)).toBe("");
  });

  it("knows when filters are active (sorting alone is not a filter)", () => {
    expect(hasActiveFilters({ ...DEFAULT_QUERY, sort: "title" })).toBe(false);
    expect(hasActiveFilters({ ...DEFAULT_QUERY, favorited: true })).toBe(true);
  });

  it("maps to the API's filter[] parameters", () => {
    expect(catalogParams({ ...DEFAULT_QUERY, domain: "linux", favorited: true, sort: "title" })).toEqual({
      q: undefined,
      "filter[domain]": "linux",
      "filter[difficulty]": undefined,
      "filter[favorited]": 1,
      sort: "title",
      page: undefined,
      per_page: 6,
    });
  });
});
