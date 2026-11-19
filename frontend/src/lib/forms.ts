import type { FieldValues, Path, UseFormSetError } from "react-hook-form";
import { ApiError } from "@/lib/api/client";

/**
 * Puts server-side validation errors (422 envelope) onto the matching form fields.
 * Returns true when every error found a field. Otherwise the caller shows a
 * form-level message. Server validation is the authority; client rules only give
 * earlier feedback.
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fields: readonly Path<T>[],
): boolean {
  if (!(error instanceof ApiError)) return false;
  const entries = Object.entries(error.fieldErrors);
  if (entries.length === 0) return false;

  let allPlaced = true;
  for (const [field, messages] of entries) {
    if ((fields as readonly string[]).includes(field)) {
      setError(field as Path<T>, { type: "server", message: messages[0] });
    } else {
      allPlaced = false;
    }
  }
  return allPlaced;
}
