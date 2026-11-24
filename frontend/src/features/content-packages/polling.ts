import type { TransferStatus } from "./types";

/**
 * How the import and export dialogs poll a transfer while the worker runs it: 1 s, then 1.5 times
 * longer each time, at most 5 s. Polling stops as soon as the dialog closes (its query unmounts).
 */
export const POLL = { firstMs: 1000, factor: 1.5, maxMs: 5000, stalledMs: 30_000 } as const;

export function pollDelay(attempt: number): number {
  return Math.min(POLL.maxMs, Math.round(POLL.firstMs * POLL.factor ** Math.max(0, attempt)));
}

/** Statuses the worker still has to finish. */
export function isWorking(status: TransferStatus): boolean {
  return status === "validating" || status === "applying" || status === "exporting";
}

/**
 * True when a step has sat in the same working status for too long: most likely the job is still
 * queued because no worker is running. `since` is when the dialog first saw this status.
 */
export function isStalled(status: TransferStatus, since: number, now: number): boolean {
  return isWorking(status) && now - since >= POLL.stalledMs;
}
