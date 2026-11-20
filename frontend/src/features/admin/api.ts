import { api, request } from "@/lib/api/client";
import type { Paginated } from "@/features/catalog/types";
import { auditQueryToApi, userQueryToApi } from "./query-state";
import type { AdminOverview, AdminUser, AdminUserDetail, AuditEntry, AuditQuery, Role, UserQuery } from "./types";

const base = "/api/v1/admin";

export const fetchOverview = (signal?: AbortSignal) => api.get<AdminOverview>(`${base}/overview`, { signal });

export const fetchUsers = (query: UserQuery, signal?: AbortSignal) =>
  request<Paginated<AdminUser>>("GET", `${base}/users`, { query: userQueryToApi(query), signal });

export const fetchUser = (id: number, signal?: AbortSignal) =>
  api.get<AdminUserDetail>(`${base}/users/${id}`, { signal });

export const changeRole = (id: number, role: Role) => api.patch<AdminUserDetail>(`${base}/users/${id}/role`, { role });

export const suspendUser = (id: number, reason: string) =>
  api.post<AdminUserDetail>(`${base}/users/${id}/suspend`, { reason });

export const reactivateUser = (id: number) => api.post<AdminUserDetail>(`${base}/users/${id}/reactivate`);

export const sendPasswordReset = (id: number) => api.post<{ message: string }>(`${base}/users/${id}/password-reset`);

export const fetchAuditLog = (query: AuditQuery, signal?: AbortSignal) =>
  request<Paginated<AuditEntry>>("GET", `${base}/audit-logs`, { query: auditQueryToApi(query), signal });
