import { api } from "@/lib/api/client";
import { env } from "@/lib/env";
import type { ExportSubject, PackageReport, Transfer } from "./types";

const base = "/api/v1/admin/content";

export const startTransfer = (file: { name: string; size: number }) =>
  api.post<Transfer>(`${base}/transfers`, { file_name: file.name, size: file.size });

/** One part of a local upload; on S3 the browser sends parts straight to their presigned URL (upload.ts). */
export const putPart = (id: number, part: number, bytes: Blob, signal?: AbortSignal) =>
  api.put<{ part: number }>(`${base}/transfers/${id}/parts/${part}`, bytes, { signal });

export const completeTransfer = (id: number, parts?: { number: number; etag: string }[]) =>
  api.post<Transfer>(`${base}/transfers/${id}/complete`, parts ? { parts } : {});

export const fetchTransfer = (id: number, signal?: AbortSignal) =>
  api.get<Transfer>(`${base}/transfers/${id}`, { signal });

export const applyTransfer = (id: number) => api.post<Transfer>(`${base}/transfers/${id}/apply`);

export const validateAgain = (id: number) => api.post<Transfer>(`${base}/transfers/${id}/validate`);

export const deleteTransfer = (id: number) => api.delete<void>(`${base}/transfers/${id}`);

export const checkPackage = (json: string) => api.post<PackageReport>(`${base}/packages/check`, { json });

export const startExport = (subject: ExportSubject) => api.post<Transfer>(`${base}/transfers/exports`, subject);

export const exportDownloadUrl = (id: number) => api.get<{ url: string }>(`${base}/transfers/${id}/download`);

/** The course overview a chatbot reads first (D71): Markdown, or the same data as JSON. */
export const courseOverviewUrl = (courseId: number, format: "md" | "json" = "md") =>
  `${env.apiUrl}${base}/courses/${courseId}/overview${format === "json" ? "?format=json" : ""}`;

/** The AI authoring guide shipped with this release. */
export const aiGuideUrl = () => `${env.apiUrl}${base}/packages/guide`;

/**
 * A lesson's or post's working copy as a .ccpkg.json download (optionally only some sections, by uuid).
 * A plain link: the browser sends the session cookie and the API answers with an attachment.
 */
export function documentPackageUrl(kind: "lessons" | "posts", id: number, sections?: string[]): string {
  const query = sections?.length ? `?sections=${encodeURIComponent(sections.join(","))}` : "";
  return `${env.apiUrl}${base}/${kind}/${id}/package${query}`;
}
