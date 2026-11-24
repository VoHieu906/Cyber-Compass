import { afterEach, describe, expect, it, vi } from "vitest";
import type { Transfer } from "./types";

const api = vi.hoisted(() => ({ startTransfer: vi.fn(), putPart: vi.fn(), completeTransfer: vi.fn() }));
vi.mock("./api", () => api);

import { uploadPackage } from "./upload";

const transfer = (upload: Transfer["upload"]): Transfer => ({
  id: 7,
  direction: "import",
  status: "uploading",
  file_name: "p.ccpkg.zip",
  size_bytes: 10,
  report: null,
  error: null,
  expires_at: "",
  created_at: null,
  upload,
});

afterEach(() => {
  vi.clearAllMocks();
  vi.unstubAllGlobals();
});

describe("uploadPackage", () => {
  it("sends local parts through the API in order, then completes without ETags", async () => {
    api.startTransfer.mockResolvedValue(
      transfer({ mode: "chunked", part_bytes: 4, parts: [1, 2, 3].map((number) => ({ number, url: null })) }),
    );
    api.completeTransfer.mockResolvedValue({ ...transfer(undefined), status: "validating" });
    const progress: number[] = [];

    const done = await uploadPackage(new File(["0123456789"], "p.ccpkg.zip"), (sent) => progress.push(sent));

    expect(api.putPart.mock.calls.map(([id, n, blob]) => [id, n, (blob as Blob).size])).toEqual([
      [7, 1, 4],
      [7, 2, 4],
      [7, 3, 2],
    ]);
    expect(progress).toEqual([4, 8, 10]);
    expect(api.completeTransfer).toHaveBeenCalledWith(7, undefined);
    expect(done.status).toBe("validating");
  });

  it("puts parts straight to S3 and completes with the ETags it got back", async () => {
    api.startTransfer.mockResolvedValue(
      transfer({
        mode: "s3",
        part_bytes: 6,
        parts: [
          { number: 1, url: "https://s3.test/a?partNumber=1" },
          { number: 2, url: "https://s3.test/a?partNumber=2" },
        ],
      }),
    );
    api.completeTransfer.mockResolvedValue(transfer(undefined));
    const fetchMock = vi.fn<(url: string, init?: RequestInit) => Promise<Response>>(
      async (url) => new Response(null, { status: 200, headers: { ETag: `"etag-${url.slice(-1)}"` } }),
    );
    vi.stubGlobal("fetch", fetchMock);

    await uploadPackage(new File(["0123456789"], "p.ccpkg.zip"), () => {});

    expect(api.putPart).not.toHaveBeenCalled();
    expect(fetchMock.mock.calls.map(([url, init]) => [url, init?.method])).toEqual([
      ["https://s3.test/a?partNumber=1", "PUT"],
      ["https://s3.test/a?partNumber=2", "PUT"],
    ]);
    expect(api.completeTransfer).toHaveBeenCalledWith(7, [
      { number: 1, etag: '"etag-1"' },
      { number: 2, etag: '"etag-2"' },
    ]);
  });

  it("fails when the storage hides the ETag (bucket CORS must expose it)", async () => {
    api.startTransfer.mockResolvedValue(
      transfer({ mode: "s3", part_bytes: 10, parts: [{ number: 1, url: "https://s3.test/a" }] }),
    );
    vi.stubGlobal(
      "fetch",
      vi.fn(async () => new Response(null, { status: 200 })),
    );

    await expect(uploadPackage(new File(["x"], "p.ccpkg.zip"), () => {})).rejects.toThrow("refused a part");
    expect(api.completeTransfer).not.toHaveBeenCalled();
  });
});
