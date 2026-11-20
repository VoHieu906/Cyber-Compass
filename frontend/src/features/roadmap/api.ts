import { api } from "@/lib/api/client";
import type { LearningPath, PathVersion } from "./types";

/** My active roadmap, or null before the first one is built. */
export const fetchLearningPath = (signal?: AbortSignal) => api.get<LearningPath | null>("/api/v1/learning-path", { signal });

/** Builds a new version from my career goal and latest assessment. */
export const generateLearningPath = () => api.post<LearningPath>("/api/v1/learning-path/generate");

export const fetchPathVersions = (signal?: AbortSignal) => api.get<PathVersion[]>("/api/v1/learning-paths", { signal });
