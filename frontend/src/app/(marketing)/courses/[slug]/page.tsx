import { Check } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import Link from "next/link";
import { notFound } from "next/navigation";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { CourseActions } from "@/features/catalog/components/course-actions";
import { CourseCard } from "@/features/catalog/components/course-card";
import { CourseThumbnail } from "@/features/catalog/components/course-thumbnail";
import { Curriculum } from "@/features/catalog/components/curriculum";
import { CourseMapSection } from "@/features/course-map/components/course-map-section";
import { DifficultyChip, Duration } from "@/features/catalog/components/labels";
import type { CourseDetail } from "@/features/catalog/types";
import { serverGetOrNull } from "@/lib/api/server";

async function getCourse(slug: string) {
  return serverGetOrNull<CourseDetail>(`/api/v1/courses/${encodeURIComponent(slug)}`);
}

export async function generateMetadata({ params }: PageProps<"/courses/[slug]">): Promise<Metadata> {
  const course = await getCourse((await params).slug);
  return course ? { title: course.title, description: course.summary } : {};
}

export default async function CoursePage({ params }: PageProps<"/courses/[slug]">) {
  const course = await getCourse((await params).slug);
  if (!course) notFound();
  const t = await getTranslations();

  return (
    <div className="container-page flex flex-col gap-[var(--space-2xl)] py-[var(--space-xl)]">
      <div className="flex flex-col gap-8">
        <Breadcrumb items={[{ href: "/courses", label: t("catalog.title") }, { label: course.title }]} label={t("common.breadcrumb")} />
        <div className="grid gap-10 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:items-start lg:gap-[var(--gap-columns)]">
          <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-center gap-3 text-sm text-ink">
              <span>{course.domain.name}</span>
              <DifficultyChip difficulty={course.difficulty} />
            </div>
            <h1 className="text-[length:var(--text-display-s)]">{course.title}</h1>
            <p className="max-w-[40rem] text-[length:var(--text-lead)] text-ink-2">{course.summary}</p>
            <dl className="flex flex-wrap gap-x-8 gap-y-2">
              <div className="flex flex-col">
                <dt className="text-sm">{t("course.lessonsLabel")}</dt>
                <dd className="measured text-[length:var(--text-lg)] text-ink">{course.lesson_count}</dd>
              </div>
              <div className="flex flex-col">
                <dt className="text-sm">{t("course.duration")}</dt>
                <dd>
                  <Duration minutes={course.estimated_minutes} className="text-[length:var(--text-lg)] text-ink" />
                </dd>
              </div>
            </dl>
            <CourseActions course={course} />
          </div>
          <div className="panel overflow-hidden max-lg:hidden">
            <CourseThumbnail course={course} />
          </div>
        </div>
      </div>

      <CourseMapSection map={course.graph} courseSlug={course.slug} courseTitle={course.title} />

      <div className="grid gap-12 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:gap-[var(--gap-columns)]">
        <div className="flex flex-col gap-12">
          <section className="flex flex-col gap-4">
            <h2 className="text-[length:var(--text-title)]">{t("course.overview")}</h2>
            <p className="max-w-[68ch] text-[1.0625rem] text-ink-2">{course.description}</p>
          </section>
          <section className="flex flex-col gap-4">
            <h2 className="text-[length:var(--text-title)]">{t("course.objectives")}</h2>
            <ul className="grid gap-3 sm:grid-cols-2">
              {course.objectives.map((objective) => (
                <li key={objective} className="flex gap-2.5 text-ink-2">
                  <Check aria-hidden className="mt-1 size-4 shrink-0 text-accent" />
                  {objective}
                </li>
              ))}
            </ul>
          </section>
          <section className="flex flex-col gap-5">
            <h2 className="text-[length:var(--text-title)]">{t("course.curriculum")}</h2>
            <Curriculum course={course} />
          </section>
        </div>

        <aside className="flex flex-col gap-10">
          <section className="flex flex-col gap-3">
            <h2 className="text-[length:var(--text-lg)]">{t("course.skills")}</h2>
            <ul className="flex flex-wrap gap-2">
              {course.skills.map((skill) => (
                <li key={skill.slug} className="rounded-chip border border-rule px-2 py-1 text-sm text-ink">
                  {skill.name}
                </li>
              ))}
            </ul>
          </section>
          <section className="flex flex-col gap-3">
            <h2 className="text-[length:var(--text-lg)]">{t("course.prerequisites")}</h2>
            {course.prerequisites.length ? (
              <ul className="flex flex-col gap-2">
                {course.prerequisites.map((p) => (
                  <li key={p.slug}>
                    <Link href={`/courses/${p.slug}`} className="text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent">
                      {p.title}
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <p>{t("course.noPrerequisites")}</p>
            )}
          </section>
        </aside>
      </div>

      {course.related.length ? (
        <section className="flex flex-col gap-5 border-t border-rule pt-[var(--space-xl)]">
          <h2 className="text-[length:var(--text-title)]">{t("course.related")}</h2>
          <ul className="panels grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
            {course.related.map((related) => (
              <li key={related.slug}>
                <CourseCard course={related} compact />
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}
