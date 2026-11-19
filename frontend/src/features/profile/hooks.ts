"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { currentUserKey } from "@/features/auth/hooks";
import type { User } from "@/features/auth/types";
import { changePassword, removeAvatar, updatePreferences, updateProfile, uploadAvatar } from "./api";

/** Every profile mutation returns the updated user; it replaces the cached /me. */
function useUserMutation<TInput>(fn: (input: TInput) => Promise<User>) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: fn,
    onSuccess: (user) => queryClient.setQueryData(currentUserKey, user),
  });
}

export const useUpdateProfile = () => useUserMutation(updateProfile);
export const useUploadAvatar = () => useUserMutation(uploadAvatar);
export const useRemoveAvatar = () => useUserMutation(() => removeAvatar());
export const useUpdatePreferences = () => useUserMutation(updatePreferences);
export const useChangePassword = () => useMutation({ mutationFn: changePassword });
