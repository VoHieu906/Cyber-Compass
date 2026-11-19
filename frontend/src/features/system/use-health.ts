"use client";

import { useQuery } from "@tanstack/react-query";
import { getHealth, type HealthStatus } from "./api";

export type HealthResult = HealthStatus & { latencyMs: number };

export function useHealth() {
  return useQuery<HealthResult>({
    queryKey: ["system", "health"],
    queryFn: async ({ signal }) => {
      const started = performance.now();
      const health = await getHealth(signal);
      return { ...health, latencyMs: Math.round(performance.now() - started) };
    },
    retry: false,
    staleTime: 0,
  });
}
