"use client";

import { ArrowRight } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Button, type ButtonProps } from "@/components/ui/button";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { useGenerateLearningPath } from "../hooks";

/**
 * Builds a roadmap version from the learner's goal and latest assessment, then opens it.
 * The roadmap never rebuilds by itself (ARCHITECTURE D26); this button is the only way.
 */
export function BuildRoadmapButton({ update = false, stay = false, ...props }: { update?: boolean; stay?: boolean } & Omit<ButtonProps, "onClick" | "children">) {
  const t = useTranslations("roadmap");
  const router = useRouter();
  const generate = useGenerateLearningPath();
  const errorMessage = useErrorMessage();
  return (
    <div className="flex flex-col items-start gap-2">
      <Button {...props} loading={generate.isPending} onClick={() => generate.mutate(undefined, { onSuccess: () => (stay ? undefined : router.push("/roadmap")) })}>
        {update ? t("update") : t("build")}
        <ArrowRight aria-hidden />
      </Button>
      {generate.isError ? <Alert tone="error">{errorMessage(generate.error)}</Alert> : null}
    </div>
  );
}
