"use client";

import { ArrowLeft, ArrowRight, CircleCheck, ExternalLink, Target } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { Button, buttonVariants } from "@/components/ui/button";
import { Duration } from "@/features/catalog/components/labels";
import { useCourseProgress } from "@/features/catalog/hooks";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ApiError } from "@/lib/api/client";
import { useCompleteLesson, useLesson, useStartLesson, useTimeOnPage } from "../hooks";
import type { Lesson } from "../types";
import { renderInline } from "./prose";
import { Section } from "./sections";

export function LessonReader({ slug }: { slug: string }) {
  const t = useTranslations("learn");
  const lesson = useLesson(slug);
  useStartLesson(lesson.data);

  if (lesson.isError) {
    const missing = lesson.error instanceof ApiError && lesson.error.status === 404;
    return (
      <div className="container-page flex max-w-[44rem] flex-col items-start gap-3 py-[var(--space-2xl)]">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        <Link href="/courses" className={buttonVariants({ variant: "secondary" })}>
          <ArrowLeft aria-hidden />
          {t("backToCourse")}
        </Link>
      </div>
    );
  }

  if (!lesson.data) {
    return <div role="status" aria-label={t("onThisPage")} className="min-h-[60vh]" />;
  }

  return <LessonView lesson={lesson.data} />;
}

function LessonView({ lesson }: { lesson: Lesson }) {
  const t = useTranslations();
  const headed = lesson.sections.filter((s) => s.heading && s.type !== "callout");

  return (
    <div className="container-page grid gap-10 py-[var(--space-xl)] lg:grid-cols-[minmax(0,15rem)_minmax(0,1fr)] lg:gap-[var(--gap-columns)]">
      <aside className="max-lg:hidden">
        <nav aria-label={t("learn.onThisPage")} className="sticky top-[calc(var(--header-height)+1.5rem)] flex flex-col gap-3">
          <p className="text-sm font-medium text-ink">{t("learn.onThisPage")}</p>
          <ol className="flex flex-col gap-1 border-l border-rule">
            {headed.map((s) => (
              <li key={s.id}>
                <a
                  href={`#${s.id}`}
                  className="-ml-px block border-l border-transparent py-1 pl-3 text-sm text-muted transition-[color,border-color] duration-[var(--dur-micro)] hover:border-rule-2 hover:text-ink"
                >
                  {s.heading}
                </a>
              </li>
            ))}
          </ol>
        </nav>
      </aside>

      <article className="flex max-w-[44rem] min-w-0 flex-col gap-10 text-[1.0625rem] leading-[1.7] text-ink-2">
        <header className="flex flex-col gap-5">
          <Breadcrumb
            label={t("common.breadcrumb")}
            items={[
              { href: "/courses", label: t("catalog.title") },
              { href: `/courses/${lesson.course.slug}`, label: lesson.course.title },
              { label: lesson.title },
            ]}
          />
          <div className="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
            <span className="measured text-ink">
              {t("learn.lessonOf", { position: lesson.navigation.position, total: lesson.navigation.total })}
            </span>
            <span>{lesson.chapter.title}</span>
            <Duration minutes={lesson.estimated_minutes} className="text-ink" />
          </div>
          <h1 className="text-[length:var(--text-display-s)]">{lesson.title}</h1>
          <p className="text-[length:var(--text-lead)]">{lesson.summary}</p>
        </header>

        <section className="flex flex-col gap-3 rounded-control border border-rule bg-paper-2 p-5">
          <h2 className="flex items-center gap-2 text-[length:var(--text-lg)]">
            <Target aria-hidden className="size-4 text-accent" />
            {t("learn.objectives")}
          </h2>
          <ul className="flex flex-col gap-1.5 pl-5 text-base [list-style:disc] marker:text-muted">
            {lesson.objectives.map((o) => (
              <li key={o}>{o}</li>
            ))}
          </ul>
          {lesson.prerequisites.length ? (
            <p className="text-sm">
              {t("learn.prerequisites")}:{" "}
              {lesson.prerequisites.map((p, i) => (
                <span key={p.slug}>
                  {i > 0 ? ", " : null}
                  <Link href={`/courses/${p.slug}`} className="text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent">
                    {p.title}
                  </Link>
                </span>
              ))}
            </p>
          ) : null}
        </section>

        {lesson.sections.map((section) => (
          <Section key={section.id} section={section} />
        ))}

        <section className="flex flex-col gap-3 border-t border-rule pt-8">
          <h2 className="text-[length:var(--text-title)]">{t("learn.takeaways")}</h2>
          <ul className="flex flex-col gap-2.5">
            {lesson.key_takeaways.map((k) => (
              <li key={k} className="flex gap-2.5">
                <CircleCheck aria-hidden className="mt-1.5 size-4 shrink-0 text-accent" />
                <span>{renderInline(k)}</span>
              </li>
            ))}
          </ul>
        </section>

        {lesson.resources.length ? (
          <section className="flex flex-col gap-3">
            <h2 className="text-[length:var(--text-lg)]">{t("learn.resources")}</h2>
            <ul className="flex flex-col gap-2 text-base">
              {lesson.resources.map((r) => (
                <li key={r.url}>
                  <a
                    href={r.url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-1.5 text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
                  >
                    {r.label}
                    <ExternalLink aria-hidden className="size-3.5" />
                    <span className="sr-only">({t("learn.opensInNewTab")})</span>
                  </a>
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        <LessonFooter lesson={lesson} />
      </article>
    </div>
  );
}

function LessonFooter({ lesson }: { lesson: Lesson }) {
  const t = useTranslations("learn");
  const format = useFormatter();
  const errorMessage = useErrorMessage();
  const elapsedSeconds = useTimeOnPage(lesson.slug);
  const complete = useCompleteLesson(lesson);
  const course = useCourseProgress(lesson.course.slug);
  const done = lesson.progress.status === "completed";
  const { previous, next } = lesson.navigation;
  const courseDone = course.data && course.data.total_lessons > 0 && course.data.completed_lessons === course.data.total_lessons;

  return (
    <footer className="flex flex-col gap-6 border-t border-rule pt-8">
      <div className="flex flex-col gap-3 rounded-control border border-rule bg-paper-2 p-5">
        {done ? (
          <p className="flex items-center gap-2 font-medium text-ink" role="status">
            <CircleCheck aria-hidden className="size-5 text-accent" />
            {t("completed")}
            {lesson.progress.completed_at ? (
              <span className="text-sm font-normal text-muted">
                {t("completedOn", { date: format.dateTime(new Date(lesson.progress.completed_at), { dateStyle: "medium" }) })}
              </span>
            ) : null}
          </p>
        ) : (
          <div>
            <Button onClick={() => complete.mutate(elapsedSeconds())} loading={complete.isPending}>
              <CircleCheck aria-hidden />
              {t("markComplete")}
            </Button>
          </div>
        )}
        {course.data ? (
          <p className="measured text-sm">
            {t("courseProgress", {
              completed: course.data.completed_lessons,
              total: course.data.total_lessons,
              course: lesson.course.title,
            })}
          </p>
        ) : null}
        {done && courseDone ? <p className="text-sm text-ink">{t("courseDone", { course: lesson.course.title })}</p> : null}
        {complete.isError ? <Alert tone="error">{errorMessage(complete.error)}</Alert> : null}
      </div>

      <nav aria-label={t("lessonNav")} className="grid gap-3 sm:grid-cols-2">
        {previous ? (
          <Link href={`/learn/${previous.slug}`} className="panel flex flex-col gap-1 p-4 transition-[border-color] duration-[var(--dur-micro)] hover:border-rule-2">
            <span className="inline-flex items-center gap-1.5 text-sm">
              <ArrowLeft aria-hidden className="size-3.5" />
              {t("previous")}
            </span>
            <span className="font-medium text-ink">{previous.title}</span>
          </Link>
        ) : (
          <span />
        )}
        {next ? (
          <Link
            href={`/learn/${next.slug}`}
            className="panel flex flex-col items-end gap-1 p-4 text-right transition-[border-color] duration-[var(--dur-micro)] hover:border-rule-2"
          >
            <span className="inline-flex items-center gap-1.5 text-sm">
              {t("next")}
              <ArrowRight aria-hidden className="size-3.5" />
            </span>
            <span className="font-medium text-ink">{next.title}</span>
          </Link>
        ) : (
          <Link href={`/courses/${lesson.course.slug}`} className="panel flex flex-col items-end gap-1 p-4 text-right hover:border-rule-2">
            <span className="text-sm">{t("backToCourse")}</span>
            <span className="font-medium text-ink">{lesson.course.title}</span>
          </Link>
        )}
      </nav>
    </footer>
  );
}
