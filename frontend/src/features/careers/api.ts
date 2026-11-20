import { api } from "@/lib/api/client";
import type { CareerSummary } from "./types";

export const fetchCareers = (signal?: AbortSignal) => api.get<CareerSummary[]>("/api/v1/careers", { signal });
