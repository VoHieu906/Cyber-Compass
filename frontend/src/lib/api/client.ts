import { env } from "@/lib/env";

/** Error envelope returned by the API (docs/API_SPECIFICATION.md#errors). */
export type ApiErrorBody = {
  message: string;
  code: string;
  errors?: Record<string, string[]>;
};

export class ApiError extends Error {
  readonly status: number;
  readonly code: string;
  readonly fieldErrors: Record<string, string[]>;

  constructor(status: number, body: Partial<ApiErrorBody>) {
    super(body.message ?? "Request failed");
    this.name = "ApiError";
    this.status = status;
    this.code = body.code ?? (status === 0 ? "network_error" : "server_error");
    this.fieldErrors = body.errors ?? {};
  }

  get isValidation(): boolean {
    return this.status === 422;
  }

  get isUnauthenticated(): boolean {
    return this.status === 401;
  }
}

type Method = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

export type RequestOptions = {
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined>;
  signal?: AbortSignal;
  headers?: Record<string, string>;
};

const XSRF_COOKIE = "XSRF-TOKEN";

function readCookie(name: string): string | undefined {
  if (typeof document === "undefined") return undefined;
  const match = document.cookie.split("; ").find((row) => row.startsWith(`${name}=`));
  return match ? decodeURIComponent(match.slice(name.length + 1)) : undefined;
}

function buildUrl(path: string, query?: RequestOptions["query"]): string {
  const url = new URL(`${env.apiUrl}${path.startsWith("/") ? path : `/${path}`}`);
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value !== undefined) url.searchParams.set(key, String(value));
  }
  return url.toString();
}

async function parseBody(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined;
  const type = response.headers.get("content-type") ?? "";
  return type.includes("application/json") ? response.json() : undefined;
}

/**
 * Low-level request. Sends the Sanctum session cookie and the XSRF header,
 * and turns non-2xx responses into ApiError. Returns the raw JSON body.
 */
export async function request<T>(method: Method, path: string, options: RequestOptions = {}): Promise<T> {
  const headers: Record<string, string> = {
    Accept: "application/json",
    // Server messages (validation, auth) come back in the language the UI is showing.
    ...(typeof document !== "undefined" && document.documentElement.lang
      ? { "Accept-Language": document.documentElement.lang }
      : {}),
    ...options.headers,
  };
  if (options.body !== undefined && !(options.body instanceof FormData)) {
    headers["Content-Type"] = "application/json";
  }
  if (method !== "GET") {
    const xsrf = readCookie(XSRF_COOKIE);
    if (xsrf) headers["X-XSRF-TOKEN"] = xsrf;
  }

  let response: Response;
  try {
    response = await fetch(buildUrl(path, options.query), {
      method,
      headers,
      credentials: "include",
      signal: options.signal,
      body:
        options.body === undefined
          ? undefined
          : options.body instanceof FormData
            ? options.body
            : JSON.stringify(options.body),
    });
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === "AbortError") throw cause;
    throw new ApiError(0, { message: "Network error" });
  }

  const body = await parseBody(response);
  if (!response.ok) {
    throw new ApiError(response.status, (body ?? {}) as Partial<ApiErrorBody>);
  }
  return body as T;
}

/** Unwraps the `{ data }` success envelope. */
async function data<T>(method: Method, path: string, options?: RequestOptions): Promise<T> {
  const body = await request<{ data: T }>(method, path, options);
  return body?.data as T;
}

export const api = {
  get: <T>(path: string, options?: RequestOptions) => data<T>("GET", path, options),
  post: <T>(path: string, body?: unknown, options?: RequestOptions) => data<T>("POST", path, { ...options, body }),
  put: <T>(path: string, body?: unknown, options?: RequestOptions) => data<T>("PUT", path, { ...options, body }),
  patch: <T>(path: string, body?: unknown, options?: RequestOptions) => data<T>("PATCH", path, { ...options, body }),
  delete: <T>(path: string, options?: RequestOptions) => data<T>("DELETE", path, options),
  /** Sanctum: sets the XSRF-TOKEN cookie before login/register. */
  csrf: () => request<void>("GET", "/sanctum/csrf-cookie"),
};
