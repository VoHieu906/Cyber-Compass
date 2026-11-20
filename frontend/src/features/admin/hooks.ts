"use client";

import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  changeRole,
  fetchAuditLog,
  fetchOverview,
  fetchUser,
  fetchUsers,
  reactivateUser,
  sendPasswordReset,
  suspendUser,
} from "./api";
import type { AdminUserDetail, AuditQuery, Role, UserQuery } from "./types";

export const adminKeys = {
  all: ["admin"] as const,
  overview: ["admin", "overview"] as const,
  users: (query: UserQuery) => ["admin", "users", query] as const,
  user: (id: number) => ["admin", "user", id] as const,
  audit: (query: AuditQuery) => ["admin", "audit", query] as const,
};

export function useAdminOverview() {
  return useQuery({ queryKey: adminKeys.overview, queryFn: ({ signal }) => fetchOverview(signal) });
}

export function useAdminUsers(query: UserQuery) {
  return useQuery({
    queryKey: adminKeys.users(query),
    queryFn: ({ signal }) => fetchUsers(query, signal),
    placeholderData: keepPreviousData,
  });
}

export function useAdminUser(id: number) {
  return useQuery({ queryKey: adminKeys.user(id), queryFn: ({ signal }) => fetchUser(id, signal) });
}

export function useAuditLog(query: AuditQuery) {
  return useQuery({
    queryKey: adminKeys.audit(query),
    queryFn: ({ signal }) => fetchAuditLog(query, signal),
    placeholderData: keepPreviousData,
  });
}

/**
 * Every account action returns the fresh user detail. It replaces the cached detail
 * at once; lists, counts and the audit log are refetched because they changed too.
 */
function useUserAction<V>(id: number, action: (variables: V) => Promise<AdminUserDetail>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: action,
    onSuccess: (user) => {
      queryClient.setQueryData(adminKeys.user(id), user);
      void queryClient.invalidateQueries({ queryKey: adminKeys.all, predicate: (q) => q.queryKey[1] !== "user" });
    },
  });
}

export const useChangeRole = (id: number) => useUserAction(id, (role: Role) => changeRole(id, role));
export const useSuspendUser = (id: number) => useUserAction(id, (reason: string) => suspendUser(id, reason));
export const useReactivateUser = (id: number) => useUserAction(id, () => reactivateUser(id));

export function useSendPasswordReset(id: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: () => sendPasswordReset(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: adminKeys.all }),
  });
}
