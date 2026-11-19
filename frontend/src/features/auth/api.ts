import { api, ApiError } from "@/lib/api/client";
import type { LoginInput, RegisterInput, ResetPasswordInput, User } from "./types";

/** The signed-in user, or null when there is no session. */
export async function fetchCurrentUser(signal?: AbortSignal): Promise<User | null> {
  try {
    return await api.get<User>("/api/v1/me", { signal });
  } catch (error) {
    if (error instanceof ApiError && error.isUnauthenticated) return null;
    throw error;
  }
}

export async function login(input: LoginInput): Promise<User> {
  await api.csrf();
  return api.post<User>("/api/v1/auth/login", input);
}

export async function register(input: RegisterInput): Promise<User> {
  await api.csrf();
  return api.post<User>("/api/v1/auth/register", input);
}

export function logout(): Promise<void> {
  return api.post<void>("/api/v1/auth/logout");
}

export async function requestPasswordReset(email: string): Promise<void> {
  await api.csrf();
  await api.post("/api/v1/auth/forgot-password", { email });
}

export async function resetPassword(input: ResetPasswordInput): Promise<void> {
  await api.csrf();
  await api.post("/api/v1/auth/reset-password", input);
}
