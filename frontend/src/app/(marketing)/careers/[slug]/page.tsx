import { Check } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { notFound } from "next/navigation";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { LevelMeter } from "@/features/careers/components/level-meter";
import { RoadmapPreview } from "@/features/careers/components/roadmap-preview";
import type { CareerDetail } from "@/features/careers/types";
import { CourseCard } from "@/features/catalog/components/course-card";
import { serverGetOrNull } from "@/lib/api/server";

const getCareer = (slug: string) => serverGetOrNull<CareerDetail>(`/api/v1/careers/${encodeURIComponent(slug)}`);

export async function generateMetadata({ params }: PageProps<"/careers/[slug]">): Promise<Metadata> {
  const career = await getCareer((await params).slug);
  return career ? { title: career.title, description: career.summary } : {};
}

export default async function CareerPage({ params }: PageProps<"/careers/[slug]">) {
  const career = await getCareer((await params).slug);
  if (!career) notFound();
  const t = await getTranslations();

  return (
    <div className="container-page flex flex-col gap-[var(--space-2xl)] py-[var(--space-xl)]">
      <header className="flex flex-col gap-6">
        <Breadcrumb label={t("common.breadcrumb")} items={[{ href: "/careers", label: t("careers.title") }, { label: career.title }]} />
        <div className="flex max-w-[44rem] flex-col gap-4">
          <h1 className="text-[length:var(--text-display-s)]">{career.title}</h1>
          <p className="text-[length:var(--text-lead)] text-ink-2">{career.summary}</p>
          <p className="max-w-[68ch]">{career.description}</p>
        </div>
      </header>

      <div className="grid gap-12 lg:grid-cols-[minmax(0,7fr)_minmax(0,5fr)] lg:gap-[var(--gap-columns)]">
        <section className="flex flex-col gap-4">
          <h2 className="text-[length:var(--text-title)]">{t("careers.responsibilities")}</h2>
          <ul className="flex flex-col gap-3">
            {career.responsibilities.map((r) => (
              <li key={r} className="flex gap-2.5 text-ink-2">
                <Check aria-hidden className="mt-1 size-4 shrink-0 text-accent" />
                {r}
              </li>
            ))}
          </ul>
        </section>
        <div className="flex flex-col gap-10">
          <section className="flex flex-col gap-4">
            <h2 className="text-[length:var(--text-title)]">{t("careers.domains")}</h2>
            <ul className="panels">
              {career.domains.map((d) => (
                <li key={d.slug} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3">
                  <span className="text-ink">{d.name}</span>
                  <LevelMeter level={d.required_level} />
                </li>
              ))}
            </ul>
          </section>
          <section className="flex flex-col gap-3">
            <h2 className="text-[length:var(--text-lg)]">{t("careers.skills")}</h2>
            <ul className="flex flex-wrap gap-2">
              {career.skills.map((s) => (
                <li key={s.slug} className="rounded-chip border border-rule px-2 py-1 text-sm text-ink">
                  {s.name}
                </li>
              ))}
            </ul>
          </section>
        </div>
      </div>

      <section className="flex flex-col gap-6 border-t border-rule pt-[var(--space-xl)]">
        <div className="flex max-w-[44rem] flex-col gap-2">
          <h2 className="text-[length:var(--text-section)]">{t("careers.roadmap")}</h2>
          <p>{t("careers.roadmapLede")}</p>
        </div>
        <RoadmapPreview stages={career.roadmap.stages} />
        <Alert tone="info" className="max-w-[44rem]">
          {t("careers.goalLater")}
        </Alert>
      </section>

      {career.suggested_courses.length ? (
        <section className="flex flex-col gap-5">
          <h2 className="text-[length:var(--text-title)]">{t("careers.suggestedCourses")}</h2>
          <ul className="panels grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
            {career.suggested_courses.map((course) => (
              <li key={course.slug}>
                <CourseCard course={course} />
              </li>
            ))}
          </ul>
        </section>
      ) : null}
    </div>
  );
}
