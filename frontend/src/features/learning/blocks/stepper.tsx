"use client";

import { ChevronLeft, ChevronRight, RotateCcw } from "lucide-react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";

/** Previous / next / start over, shared by the step-by-step blocks (sequence, step-through). */
export function Stepper({
  current,
  total,
  onChange,
}: {
  current: number;
  total: number;
  onChange: (step: number) => void;
}) {
  const t = useTranslations("learn.diagrams");
  return (
    <div className="flex flex-wrap items-center gap-2">
      <Button variant="secondary" size="sm" disabled={current === 0} onClick={() => onChange(current - 1)}>
        <ChevronLeft aria-hidden />
        {t("previousStep")}
      </Button>
      {current < total - 1 ? (
        <Button size="sm" onClick={() => onChange(current + 1)}>
          {t("nextStep")}
          <ChevronRight aria-hidden />
        </Button>
      ) : (
        <Button variant="secondary" size="sm" onClick={() => onChange(0)}>
          <RotateCcw aria-hidden />
          {t("restart")}
        </Button>
      )}
      <span className="measured ml-auto text-sm text-muted">{t("step", { current: current + 1, total })}</span>
    </div>
  );
}
