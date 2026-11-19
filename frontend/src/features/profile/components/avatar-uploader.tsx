"use client";

import { useTranslations } from "next-intl";
import { useRef, useState } from "react";
import { Avatar } from "@/components/shared/avatar";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import type { User } from "@/features/auth/types";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { useRemoveAvatar, useUploadAvatar } from "../hooks";

const ACCEPT = "image/jpeg,image/png,image/webp";
const MAX_BYTES = 2 * 1024 * 1024;

export function AvatarUploader({ user }: { user: User }) {
  const t = useTranslations("settings.profile");
  const errorMessage = useErrorMessage();
  const upload = useUploadAvatar();
  const remove = useRemoveAvatar();
  const inputRef = useRef<HTMLInputElement>(null);
  const [message, setMessage] = useState<{ tone: "error" | "success"; text: string } | null>(null);

  async function onFile(file: File | undefined) {
    if (!file) return;
    setMessage(null);
    // Quick checks for instant feedback; the API validates and re-encodes regardless.
    if (!ACCEPT.split(",").includes(file.type) || file.size > MAX_BYTES) {
      setMessage({ tone: "error", text: t("avatarHint") });
      return;
    }
    try {
      await upload.mutateAsync(file);
      setMessage({ tone: "success", text: t("avatarUpdated") });
    } catch (error) {
      setMessage({ tone: "error", text: errorMessage(error) });
    } finally {
      if (inputRef.current) inputRef.current.value = "";
    }
  }

  async function onRemove() {
    setMessage(null);
    try {
      await remove.mutateAsync(undefined);
      setMessage({ tone: "success", text: t("avatarRemoved") });
    } catch (error) {
      setMessage({ tone: "error", text: errorMessage(error) });
    }
  }

  const hasAvatar = Boolean(user.profile.avatar_url);

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-wrap items-center gap-5">
        <Avatar name={user.name} src={user.profile.avatar_url} size={72} />
        <div className="flex flex-col gap-2">
          <div className="flex flex-wrap gap-2">
            <Button variant="secondary" size="sm" loading={upload.isPending} onClick={() => inputRef.current?.click()}>
              {hasAvatar ? t("avatarReplace") : t("avatarUpload")}
            </Button>
            {hasAvatar ? (
              <Button variant="ghost" size="sm" loading={remove.isPending} onClick={onRemove}>
                {t("avatarRemove")}
              </Button>
            ) : null}
          </div>
          <p className="text-sm">{t("avatarHint")}</p>
        </div>
        <input
          ref={inputRef}
          type="file"
          accept={ACCEPT}
          className="sr-only"
          tabIndex={-1}
          aria-hidden
          onChange={(e) => onFile(e.target.files?.[0])}
        />
      </div>
      {message ? <Alert tone={message.tone}>{message.text}</Alert> : null}
    </div>
  );
}
