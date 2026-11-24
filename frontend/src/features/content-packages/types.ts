/** Content packages (F6, D67, D70): API shapes from backend TransferResource and PackagePlan. */

export type TransferStatus = "uploading" | "validating" | "ready" | "applying" | "applied" | "exporting" | "failed";

export type SectionChange = {
  change: "added" | "replaced" | "removed" | "kept";
  key: string;
  uuid: string;
  type: string | null;
  heading: string | null;
};

export type ReportDocument = {
  type: "lesson" | "post";
  slug: string;
  title: string;
  id: number | null;
  is_new: boolean;
  chapter: { id: number; title: string } | null;
  lock_version: number;
  fingerprint: string | null;
  changes: SectionChange[];
};

/** The preview of an import: errors by place in the package, warnings, and what changes per document. */
export type PackageReport = {
  kind: string | null;
  generator: string | null;
  valid: boolean;
  /** At most 100 messages (backend PackagePlan::MAX_REPORTED_ERRORS); the rest are only counted. */
  errors: Record<string, string[]>;
  more_errors?: number;
  warnings: { path: string; message: string }[];
  documents: ReportDocument[];
};

/** What a zip export holds. */
export type ExportSummary = { kind: string; documents: number; images: number };

export type UploadPlan = {
  mode: "s3" | "chunked";
  part_bytes: number;
  parts: { number: number; url: string | null }[];
};

export type Transfer = {
  id: number;
  direction: "import" | "export";
  status: TransferStatus;
  file_name: string;
  size_bytes: number | null;
  report: PackageReport | ExportSummary | null;
  error: string | null;
  expires_at: string;
  created_at: string | null;
  upload?: UploadPlan;
};

export type ExportSubject = { type: "lesson" | "post" | "course"; id: number; sections?: string[] };
