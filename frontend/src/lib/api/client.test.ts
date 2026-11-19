import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { api, ApiError } from "./client";

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { "content-type": "application/json" },
  });
}

describe("api client", () => {
  const fetchMock = vi.fn<typeof fetch>();

  beforeEach(() => {
    vi.stubGlobal("fetch", fetchMock);
    document.cookie = "XSRF-TOKEN=abc%3D123; path=/";
  });

  afterEach(() => {
    fetchMock.mockReset();
    vi.unstubAllGlobals();
    document.cookie = "XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/";
  });

  it("unwraps the data envelope and sends credentials", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: { status: "ok" } }));

    await expect(api.get("/api/v1/health")).resolves.toEqual({ status: "ok" });

    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toMatch(/\/api\/v1\/health$/);
    expect(init?.credentials).toBe("include");
    expect((init?.headers as Record<string, string>)["X-XSRF-TOKEN"]).toBeUndefined();
  });

  it("sends the decoded XSRF token on mutating requests", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: null }));

    await api.post("/api/v1/auth/logout");

    const headers = fetchMock.mock.calls[0][1]?.headers as Record<string, string>;
    expect(headers["X-XSRF-TOKEN"]).toBe("abc=123");
  });

  it("serializes query parameters and skips undefined values", async () => {
    fetchMock.mockResolvedValueOnce(jsonResponse(200, { data: [] }));

    await api.get("/api/v1/courses", { query: { q: "dns", page: 2, sort: undefined } });

    const url = new URL(String(fetchMock.mock.calls[0][0]));
    expect(url.searchParams.get("q")).toBe("dns");
    expect(url.searchParams.get("page")).toBe("2");
    expect(url.searchParams.has("sort")).toBe(false);
  });

  it("maps validation failures to ApiError with field errors", async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse(422, {
        message: "The given data was invalid.",
        code: "validation_failed",
        errors: { email: ["The email has already been taken."] },
      }),
    );

    const error = await api.post("/api/v1/auth/register", {}).catch((e: unknown) => e);

    expect(error).toBeInstanceOf(ApiError);
    expect((error as ApiError).isValidation).toBe(true);
    expect((error as ApiError).fieldErrors.email).toEqual(["The email has already been taken."]);
  });

  it("reports network failures as status 0", async () => {
    fetchMock.mockRejectedValueOnce(new TypeError("Failed to fetch"));

    const error = (await api.get("/api/v1/health").catch((e: unknown) => e)) as ApiError;

    expect(error.status).toBe(0);
    expect(error.code).toBe("network_error");
  });
});
