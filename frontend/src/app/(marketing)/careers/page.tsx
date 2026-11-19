import { ArrowRight } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import Link from "next/link";
import { LevelMeter } from "@/features/careers/components/level-meter";
import type { CareerSummary } from "@/features/careers/types";
import { serverGet } from "@/lib/api/server";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("careers");
  return { title: t("title"), description: t("lede") };
}

export default async function CareersPage() {
  const [t, careers] = await Promise.all([getTranslations(), serverGet<CareerSummary[]>("/api/v1/careers")]);

  return (
    <div className="container-page flex flex-col gap-10 py-[var(--space-2xl)]">
      <header className="flex max-w-[40rem] flex-col gap-3">
        <h1 className="text-[length:var(--text-display-s)]">{t("careers.title")}</h1>
        <p className="text-[length:var(--text-lead)] text-ink-2">{t("careers.lede")}</p>
      </header>

      <ul className="panels md:grid-cols-2">
        {careers.map((career) => (
          <li key={career.slug} className="relative flex flex-col gap-5 p-6 transition-[background-color] duration-[var(--dur-micro)] hover:bg-paper-3">
            <div className="flex flex-col gap-2">
              <h2 className="text-[length:var(--text-title)]">
                <Link href={`/careers/${career.slug}`} className="rounded-chip after:absolute after:inset-0 after:content-['']">
                  {career.title}
                </Link>
              </h2>
              <p>{career.summary}</p>
            </div>
            <div className="flex flex-col gap-2">
              <p className="text-sm font-medium text-ink">{t("careers.keyDomains")}</p>
              <ul className="flex flex-col gap-1.5">
                {career.domains.slice(0, 3).map((d) => (
                  <li key={d.slug} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-sm">
                    <span className="text-ink">{d.name}</span>
                    <LevelMeter level={d.required_level} />
                  </li>
                ))}
              </ul>
            </div>
            <span className="mt-auto inline-flex items-center gap-1.5 text-sm text-ink">
              <span className="measured">{t("units.topics", { count: career.topic_count })}</span>
              <ArrowRight aria-hidden className="ml-auto size-4 text-muted" />
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
