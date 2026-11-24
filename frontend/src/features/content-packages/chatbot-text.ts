import type { PackageReport } from "./types";

/** The package format this app reads (backend PackagePlanner::FORMAT and VERSION). */
export const PACKAGE_FORMAT = "cybercompass.content";
export const PACKAGE_VERSION = 1;

/** The most error lines the text carries (the API already sends at most 100). */
export const MAX_ERROR_LINES = 100;

/**
 * The errors of a rejected package as plain text to paste back into the chatbot that wrote it.
 * Always English, whatever the interface language: it is read by the chatbot, which writes the
 * content in English (O2).
 */
export function chatbotErrorText(report: Pick<PackageReport, "errors" | "more_errors">): string {
  const all = Object.entries(report.errors).flatMap(([path, messages]) =>
    messages.map((message) => `- ${path}: ${message}`),
  );
  // A badly broken package can fail thousands of checks: the first ones are enough to act on.
  const lines = all.slice(0, MAX_ERROR_LINES);
  const more = all.length - lines.length + (report.more_errors ?? 0);
  const total = lines.length + more;
  const count = total === 1 ? "1 error" : `${total} errors`;

  return [
    `CyberCompass content package (format "${PACKAGE_FORMAT}", version ${PACKAGE_VERSION}): ${count}.`,
    ...lines,
    ...(more > 0
      ? [`…and ${more} more ${more === 1 ? "error" : "errors"}. Fix these first; checking again will list the rest.`]
      : []),
    "",
    "Fix only these errors, keep every uuid and key unchanged, and return the complete package as a single JSON document.",
  ].join("\n");
}
