import { api } from "@/lib/api/client";

export type HealthStatus = {
  status: "ok" | "degraded";
  database: "ok" | "down";
  version: string;
};

export function getHealth(signal?: AbortSignal): Promise<HealthStatus> {
  return api.get<HealthStatus>("/api/v1/health", { signal });
}
