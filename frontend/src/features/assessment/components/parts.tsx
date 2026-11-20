"use client";

import { Bookmark, CircleAlert, CircleDot } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { Alert } from "@/components/ui/alert";
import { Button, type ButtonProps } from "@/components/ui/button";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { cn } from "@/lib/utils";
import { useStartAttempt } from "../hooks";
import type { Band, Formula } from "../types";

/**
 * Bands keep the design system's two meanings apart: a gap is attention (amber), a strength is
 * the learner's own progress (cyan), and developing stays neutral. Icon plus text, never colour alone.
 */
const bandStyle: Record<Band, { chip: string; bar: string; Icon: typeof CircleDot }> = {
  gap: { chip: "border-attn bg-attn-soft", bar: "bg-attn", Icon: CircleAlert },
  developing: { chip: "border-rule-2 bg-paper-3", bar: "bg-ink-2", Icon: CircleDot },
  strength: { chip: "border-accent bg-accent-soft", bar: "bg-accent", Icon: Bookmark },
};

export function BandChip({ band, className }: { band: Band; className?: string }) {
  const t = useTranslations("assessment.band");
  const { chip, Icon } = bandStyle[band];
  return (
    <span className={cn("inline-flex items-center gap-1.5 rounded-chip border px-2 py-0.5 text-sm font-medium text-ink", chip, className)}>
      <Icon aria-hidden className="size-3.5" />
      {t(band)}
    </span>
  );
}

/** A 4px track with the fill tinted by band. Decorative: the percentage is always printed beside it. */
export function ScoreBar({ score, band, className }: { score: number; band: Band; className?: string }) {
  return (
    <span aria-hidden className={cn("block h-1 w-full overflow-hidden rounded-full bg-rule", className)}>
      <span
        className={cn("block h-full origin-left rounded-full transition-transform duration-[var(--dur-long)] ease-out", bandStyle[band].bar)}
        style={{ transform: `scaleX(${Math.max(0, Math.min(score, 100)) / 100})` }}
      />
    </span>
  );
}

/** The scoring rules, with the band edges as the API reports them. */
export function FormulaPanel({ formula, className }: { formula: Formula; className?: string }) {
  const t = useTranslations("assessment.formula");
  const { developing_from: developingFrom, strength_from: strengthFrom } = formula;
  return (
    <section aria-labelledby="formula-heading" className={cn("flex flex-col gap-3 rounded-card border border-rule bg-paper-2 p-5 text-sm", className)}>
      <h2 id="formula-heading" className="text-[length:var(--text-lg)]">
        {t("heading")}
      </h2>
      <p className="measured rounded-control border border-rule bg-paper-3 px-3 py-2 text-ink">
        score = Σ weight(correct) / Σ weight(all) × 100
      </p>
      <ul className="flex flex-col gap-2 pl-5 [list-style:disc] marker:text-muted">
        <li>{t("weight")}</li>
        <li>{t("unanswered")}</li>
        <li>{t("domain")}</li>
        <li>{t("bands", { developing: developingFrom, developingTop: strengthFrom - 1, strength: strengthFrom })}</li>
      </ul>
      <BandScale developingFrom={developingFrom} strengthFrom={strengthFrom} />
    </section>
  );
}

function BandScale({ developingFrom, strengthFrom }: { developingFrom: number; strengthFrom: number }) {
  const t = useTranslations("assessment.band");
  const segments: { band: Band; from: number; to: number }[] = [
    { band: "gap", from: 0, to: developingFrom },
    { band: "developing", from: developingFrom, to: strengthFrom },
    { band: "strength", from: strengthFrom, to: 100 },
  ];
  return (
    <div aria-hidden className="flex flex-col gap-1.5 pt-1">
      <div className="flex h-1 gap-px overflow-hidden rounded-full">
        {segments.map((s) => (
          <span key={s.band} className={bandStyle[s.band].bar} style={{ flexGrow: s.to - s.from }} />
        ))}
      </div>
      <div className="flex text-xs text-muted">
        {segments.map((s) => (
          <span key={s.band} className="flex flex-col" style={{ flexGrow: s.to - s.from, flexBasis: 0 }}>
            <span className="measured">{s.from}</span>
            <span>{t(s.band)}</span>
          </span>
        ))}
      </div>
    </div>
  );
}

/** Progress toward a whole, in the learner's colour. Decorative: the count is printed beside it. */
export function ProgressTrack({ value, className }: { value: number; className?: string }) {
  return (
    <span aria-hidden className={cn("block h-1 w-full overflow-hidden rounded-full bg-rule", className)}>
      <span
        className="block h-full origin-left rounded-full bg-accent transition-transform duration-[var(--dur-short)] ease-out"
        style={{ transform: `scaleX(${Math.max(0, Math.min(value, 100)) / 100})` }}
      />
    </span>
  );
}

/** A code snippet under a question prompt. */
export function QuestionCode({ code }: { code: string }) {
  return (
    <pre className="overflow-x-auto rounded-control border border-rule bg-paper-3 p-3 text-sm leading-relaxed">
      <code className="measured text-ink">{code}</code>
    </pre>
  );
}

/** Starts (or resumes) an attempt and opens it. */
export function StartAttemptButton({ slug, children, ...props }: { slug: string } & Omit<ButtonProps, "onClick">) {
  const t = useTranslations("assessment.intro");
  const router = useRouter();
  const start = useStartAttempt();
  const errorMessage = useErrorMessage();
  return (
    <div className="flex flex-col items-start gap-2">
      <Button
        {...props}
        loading={start.isPending}
        onClick={() => start.mutate(slug, { onSuccess: (attempt) => router.push(`/assessment/attempts/${attempt.id}`) })}
      >
        {children}
      </Button>
      {start.isError ? <Alert tone="error">{errorMessage(start.error) || t("startError")}</Alert> : null}
    </div>
  );
}
