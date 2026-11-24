import { useQuery } from "@tanstack/react-query";
import { useRef, useState } from "react";
import { fetchTransfer } from "./api";
import { isStalled, isWorking, pollDelay } from "./polling";
import type { Transfer } from "./types";

/**
 * Follows a transfer while the worker runs it: polls with back-off (polling.ts), stops once the
 * step ends or the component using it unmounts (the dialog closed), and says when a step looks
 * stuck in the queue.
 */
export function useTransfer(id: number | null, initial?: Transfer) {
  // How often the interval has asked since the status last changed (read only by the interval).
  const polls = useRef<{ status: string; count: number } | null>(null);

  const query = useQuery({
    queryKey: ["admin", "content", "transfer", id],
    queryFn: ({ signal }) => fetchTransfer(id!, signal),
    enabled: id !== null,
    initialData: initial,
    // Every answer is fresh; only the interval decides when to ask again.
    staleTime: 0,
    gcTime: 0,
    refetchOnWindowFocus: false,
    refetchInterval: (q) => {
      const status = q.state.data?.status;
      if (!status || !isWorking(status)) return false;
      if (polls.current?.status !== status) polls.current = { status, count: 0 };
      return pollDelay(polls.current.count++);
    },
  });

  // When this status was first seen: adjusted during render when the status changes (React's
  // "storing information from previous renders"), so no effect or ref is read while rendering.
  const status = query.data?.status;
  const [seen, setSeen] = useState({ status, since: query.dataUpdatedAt });
  if (seen.status !== status) setSeen({ status, since: query.dataUpdatedAt });

  // dataUpdatedAt moves on every answer, so the stalled state is checked again on each poll.
  const stalled = !!status && seen.status === status && isStalled(status, seen.since, query.dataUpdatedAt);

  return { transfer: query.data, stalled, error: query.error };
}
