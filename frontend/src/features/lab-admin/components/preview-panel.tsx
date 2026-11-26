"use client";

import { RotateCcw } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { LabPlayground, type LabTransport } from "@/features/labs/components/lab-view";
import { ApiError } from "@/lib/api/client";
import { usePreview, usePreviewActions } from "../hooks";

/**
 * An admin plays the saved working copy (D79). Nothing is written and no event fires. When the
 * cached state has expired, or the draft changed under it, the server answers 409: the panel says
 * so in words and offers "Restart preview", never an error code.
 */
export function PreviewPanel({ id, lockVersion, dirty }: { id: number; lockVersion: number; dirty: boolean }) {
  const t = useTranslations("labAdmin.preview");
  const preview = usePreview(id, lockVersion);
  const { act, reset } = usePreviewActions(id, lockVersion);
  const [expired, setExpired] = useState(false);

  const transport: LabTransport = {
    act: async (version, action) => {
      try {
        return await act.mutateAsync({ version, action });
      } catch (error) {
        if (error instanceof ApiError && error.status === 409) setExpired(true);
        throw error;
      }
    },
    acting: act.isPending,
    reset: () => reset.mutateAsync().then(() => setExpired(false)),
    resetting: reset.isPending,
    // The panel's own message below replaces the learner's "changed in another tab".
    staleMessage: "",
  };

  if (preview.isError)
    return (
      <div className="flex flex-col items-start gap-3">
        <Alert tone="error">{t("loadError")}</Alert>
        <Button variant="secondary" onClick={() => preview.refetch()}>
          {t("retry")}
        </Button>
      </div>
    );
  if (!preview.data) return <div role="status" aria-label={t("loading")} className="h-64 rounded-card bg-paper-3" />;

  return (
    <div className="flex flex-col gap-4">
      {dirty ? <Alert tone="info">{t("unsaved")}</Alert> : <p className="text-sm">{t("intro")}</p>}
      {expired ? (
        <Alert tone="info">
          <span className="flex flex-wrap items-center justify-between gap-3">
            <span>{t("expired")}</span>
            <Button
              size="sm"
              className="-my-1.5"
              onClick={() => reset.mutate(undefined, { onSuccess: () => setExpired(false) })}
              loading={reset.isPending}
            >
              <RotateCcw aria-hidden />
              {t("restart")}
            </Button>
          </span>
        </Alert>
      ) : (
        <div>
          <Button variant="ghost" size="sm" onClick={() => reset.mutate()} loading={reset.isPending}>
            <RotateCcw aria-hidden />
            {t("restart")}
          </Button>
        </div>
      )}
      <LabPlayground lab={preview.data} transport={transport} />
    </div>
  );
}
