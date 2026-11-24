import { ApiError } from "@/lib/api/client";
import { completeTransfer, putPart, startTransfer } from "./api";
import type { Transfer } from "./types";

/**
 * Uploads a package file in parts (D70). On S3 each part goes straight from the browser to its
 * presigned URL, so no package bytes pass the API, the load balancer or the WAF; the bucket's CORS
 * exposes the ETag that completing the upload needs. Locally the API takes the parts.
 * Parts go one after another: progress stays honest and a large file never holds many parts in memory.
 */
export async function uploadPackage(
  file: File,
  onProgress: (sentBytes: number) => void,
  signal?: AbortSignal,
): Promise<Transfer> {
  const transfer = await startTransfer(file);
  const plan = transfer.upload!;
  const etags: { number: number; etag: string }[] = [];
  let sent = 0;

  for (const part of plan.parts) {
    const bytes = file.slice((part.number - 1) * plan.part_bytes, part.number * plan.part_bytes);
    if (plan.mode === "s3") {
      etags.push({ number: part.number, etag: await putToS3(part.url!, bytes, signal) });
    } else {
      await putPart(transfer.id, part.number, bytes, signal);
    }
    sent += bytes.size;
    onProgress(sent);
  }

  return completeTransfer(transfer.id, plan.mode === "s3" ? etags : undefined);
}

/** One presigned PUT to S3 (not the API: no cookie, no XSRF header). Returns the part's ETag. */
async function putToS3(url: string, bytes: Blob, signal?: AbortSignal): Promise<string> {
  let response: Response;
  try {
    response = await fetch(url, { method: "PUT", body: bytes, signal });
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === "AbortError") throw cause;
    throw new ApiError(0, { message: "Network error" });
  }
  const etag = response.headers.get("ETag");
  if (!response.ok || !etag)
    throw new ApiError(response.status || 0, { message: "The storage refused a part of the upload." });
  return etag;
}
