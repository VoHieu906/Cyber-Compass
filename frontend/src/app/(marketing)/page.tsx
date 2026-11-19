import { ArrowRight } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import Link from "next/link";
import { DisclosureRows } from "@/components/shared/disclosure-rows";
import { buttonVariants } from "@/components/ui/button";
import { CareerMap } from "@/features/careers/components/career-map";
import type { CareerDetail, CareerSummary } from "@/features/careers/types";
import { CourseCard } from "@/features/catalog/components/course-card";
import { DomainIcon } from "@/features/catalog/domain-icons";
import type { CourseDetail, CourseSummary, Domain } from "@/features/catalog/types";
import { serverGet } from "@/lib/api/server";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("landing");
  return { title: { absolute: t("metaTitle") }, description: t("heroLede") };
}

/** Everything on this page is read from the API: careers and their topic graphs, domains, courses. */
async function loadLanding() {
  const [careers, domains, featured] = await Promise.all([
    serverGet<CareerSummary[]>("/api/v1/careers"),
    serverGet<Domain[]>("/api/v1/domains"),
    serverGet<CourseSummary[]>("/api/v1/courses?filter[difficulty]=beginner&per_page=3"),
  ]);
  const [details, firstCourse] = await Promise.all([
    Promise.all(careers.map((c) => serverGet<CareerDetail>(`/api/v1/careers/${c.slug}`))),
    featured[0] ? serverGet<CourseDetail>(`/api/v1/courses/${featured[0].slug}`) : Promise.resolve(null),
  ]);
  return { careers: details, domains, featured, firstLesson: firstCourse?.chapters[0]?.lessons[0] ?? null, firstCourse };
}

export default async function LandingPage() {
  const [t, tUnits, data] = await Promise.all([getTranslations("landing"), getTranslations("units"), loadLanding()]);

  return (
    <>
      {/* Hero: the orientation phrase and the action beside the map. */}
      <section className="container-page grid grid-cols-[minmax(0,1fr)] items-center gap-[var(--space-xl)] py-[var(--space-2xl)] min-[60rem]:min-h-[calc(100svh-var(--header-height))] min-[60rem]:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] min-[60rem]:gap-[var(--space-2xl)]">
        <div className="flex max-w-[30rem] flex-col gap-6">
          <h1 className="text-[length:var(--text-display)]">{t("heroTitle")}</h1>
          <p className="text-[length:var(--text-lead)] text-ink-2">{t("heroLede")}</p>
          <div className="flex flex-wrap gap-3">
            <Link href="/register" className={buttonVariants()}>
              {t("ctaPrimary")}
              <ArrowRight aria-hidden />
            </Link>
            <Link href="/courses" className={buttonVariants({ variant: "secondary" })}>
              {t("ctaSecondary")}
            </Link>
          </div>
        </div>
        <CareerMap careers={data.careers} />
      </section>

      {/* Index: the eight domains, each a way into the catalog. */}
      <section className="border-t border-rule bg-paper-2">
        <div className="container-page flex flex-col gap-8 py-[var(--section-gap)]">
          <div className="flex max-w-[40rem] flex-col gap-3">
            <h2 className="text-[length:var(--text-section)]">{t("domainsTitle")}</h2>
            <p className="text-[length:var(--text-lead)]">{t("domainsLede")}</p>
          </div>
          <ul className="grid border-t border-rule sm:grid-cols-2">
            {data.domains.map((domain) => (
              <li key={domain.slug} className="border-b border-rule sm:odd:border-r sm:odd:pr-6 sm:even:pl-6">
                <Link
                  href={`/courses?domain=${domain.slug}`}
                  className="group flex items-center gap-4 py-5 transition-[color] duration-[var(--dur-micro)]"
                >
                  <DomainIcon slug={domain.slug} className="size-5 shrink-0 text-muted group-hover:text-accent" />
                  <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="text-[length:var(--text-lg)] font-medium text-ink group-hover:underline group-hover:decoration-accent group-hover:underline-offset-4">
                      {domain.name}
                    </span>
                                      </span>
                  <span className="measured shrink-0 text-sm text-ink">{tUnits("courses", { count: domain.course_count })}</span>
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Featured: three beginner courses, newest first. */}
      <section className="container-page flex flex-col gap-8 py-[var(--section-gap)]">
        <div className="flex flex-wrap items-end justify-between gap-4">
          <h2 className="text-[length:var(--text-section)]">{t("featuredTitle")}</h2>
          <Link href="/courses?difficulty=beginner" className="inline-flex items-center gap-1.5 text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent">
            {t("allCourses")}
            <ArrowRight aria-hidden className="size-4" />
          </Link>
        </div>
        <ul className="panels grid-cols-1 md:grid-cols-3">
          {data.featured.map((course) => (
            <li key={course.slug}>
              <CourseCard course={course} compact showSummary={false} />
            </li>
          ))}
        </ul>
      </section>

      {/* Questions as rows. */}
      <section className="border-t border-rule">
        <div className="container-page grid gap-8 py-[var(--section-gap)] lg:grid-cols-[minmax(0,4fr)_minmax(0,8fr)] lg:gap-[var(--gap-columns)]">
          <h2 className="text-[length:var(--text-section)]">{t("faqTitle")}</h2>
          <DisclosureRows rows={(["1", "2", "3", "4"] as const).map((n) => ({ question: t(`faq.q${n}`), answer: t(`faq.a${n}`) }))} />
        </div>
      </section>

      {/* Close: the concrete next step. */}
      {data.firstLesson && data.firstCourse ? (
        <section className="border-t border-rule bg-paper-3">
          <div className="container-page flex flex-col items-start gap-6 py-[var(--section-gap)] md:flex-row md:items-end md:justify-between">
            <div className="flex max-w-[36rem] flex-col gap-3">
              <h2 className="text-[length:var(--text-section)]">{t("closeTitle")}</h2>
              <p className="text-[length:var(--text-lead)]">
                {t("closeBody", { lesson: data.firstLesson.title, course: data.firstCourse.title })}
              </p>
            </div>
            <Link href={`/learn/${data.firstLesson.slug}`} className={buttonVariants()}>
              {t("closeAction")}
              <ArrowRight aria-hidden />
            </Link>
          </div>
        </section>
      ) : null}
    </>
  );
}
