"use client";

import { ArrowRight, Target } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { useCurrentUser } from "@/features/auth/hooks";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { useUpdateLearningGoal } from "@/features/profile/hooks";

/**
 * "Set as my goal" on a career page. The public page is the same for everyone; this island
 * reads the signed-in learner's goal on the client (ARCHITECTURE §5).
 */
export function CareerGoalPanel({ career }: { career: { slug: string; title: string } }) {
  const t = useTranslations("careers.goal");
  const { data: user, isPending } = useCurrentUser();
  const update = useUpdateLearningGoal();
  const errorMessage = useErrorMessage();

  if (isPending) return <div aria-hidden className="h-[104px]" />;

  const current = user?.learning_goal?.career?.slug === career.slug;
  const otherGoal = user?.learning_goal?.career && !current ? user.learning_goal.career.title : null;

  return (
    <section aria-label={t("label")} className="flex max-w-[44rem] flex-col gap-3 rounded-card border border-rule bg-paper-2 p-5">
      {!user ? (
        <>
          <p>{t("guest")}</p>
          <Link href={`/login?next=${encodeURIComponent(`/careers/${career.slug}`)}`} className={buttonVariants({ className: "self-start" })}>
            {t("signIn")}
            <ArrowRight aria-hidden />
          </Link>
        </>
      ) : current ? (
        <>
          <p className="flex items-center gap-2 font-medium text-ink">
            <Target aria-hidden className="size-4 text-accent" />
            {t("isGoal", { career: career.title })}
          </p>
          <p className="text-sm">{t("isGoalLede")}</p>
          <Link href="/roadmap" className={buttonVariants({ className: "self-start" })}>
            {t("openRoadmap")}
            <ArrowRight aria-hidden />
          </Link>
        </>
      ) : (
        <>
          <p>{otherGoal ? t("replace", { current: otherGoal }) : t("lede")}</p>
          <Button className="self-start" loading={update.isPending} onClick={() => update.mutate({ career: career.slug })}>
            <Target aria-hidden />
            {t("set")}
          </Button>
          {update.isError ? <Alert tone="error">{errorMessage(update.error)}</Alert> : null}
        </>
      )}
    </section>
  );
}
