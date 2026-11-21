"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { fetchKnowledgeMap, fetchLearningPath, fetchPathVersions, generateLearningPath } from "./api";

export const roadmapKeys = {
  active: ["roadmap", "active"] as const,
  versions: ["roadmap", "versions"] as const,
  map: ["roadmap", "map"] as const,
};

export function useLearningPath(enabled = true) {
  return useQuery({ queryKey: roadmapKeys.active, queryFn: ({ signal }) => fetchLearningPath(signal), enabled });
}

export function usePathVersions() {
  return useQuery({ queryKey: roadmapKeys.versions, queryFn: ({ signal }) => fetchPathVersions(signal) });
}

export function useGenerateLearningPath() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: generateLearningPath,
    onSuccess: (path) => {
      queryClient.setQueryData(roadmapKeys.active, path);
      void queryClient.invalidateQueries({ queryKey: roadmapKeys.versions });
      void queryClient.invalidateQueries({ queryKey: roadmapKeys.map });
    },
  });
}

export function useKnowledgeMap() {
  return useQuery({ queryKey: roadmapKeys.map, queryFn: ({ signal }) => fetchKnowledgeMap(signal) });
}
