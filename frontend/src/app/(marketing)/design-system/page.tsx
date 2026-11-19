import { CircleCheck, CircleX } from "lucide-react";
import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { Button } from "@/components/ui/button";
import { Field } from "@/components/ui/input";
import { RoadmapNodeCard } from "@/features/roadmap/components/node-status";

export async function generateMetadata(): Promise<Metadata> {
  const t = await getTranslations("designSystem");
  return { title: t("title") };
}

const colourRoles = [
  ["paper", "bg-paper"],
  ["paper-2", "bg-paper-2"],
  ["paper-3", "bg-paper-3"],
  ["ink", "bg-ink"],
  ["muted", "bg-muted"],
  ["rule-2", "bg-rule-2"],
  ["accent", "bg-accent"],
  ["accent-soft", "bg-accent-soft"],
  ["attn", "bg-attn"],
  ["ok", "bg-ok"],
  ["bad", "bg-bad"],
] as const;

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-5">
      <h2 className="text-[length:var(--text-title)]">{title}</h2>
      {children}
    </section>
  );
}

/** A live reference of the tokens and component states, used to review both themes and both languages. */
export default async function DesignSystemPage() {
  const t = await getTranslations("designSystem");
  return (
    <div className="container-page flex flex-col gap-[var(--space-2xl)] py-[var(--space-2xl)]">
      <header className="flex max-w-[40rem] flex-col gap-4">
        <h1 className="text-[length:var(--text-display-s)]">{t("title")}</h1>
        <p className="text-[length:var(--text-lead)] text-ink-2">{t("lede")}</p>
      </header>

      <Section title={t("colours")}>
        <ul className="grid grid-cols-[repeat(auto-fill,minmax(8.5rem,1fr))] gap-3">
          {colourRoles.map(([name, bg]) => (
            <li key={name} className="panel flex flex-col gap-2 p-3">
              <span className={`h-12 rounded-control border border-rule ${bg}`} />
              <span className="measured text-sm text-ink">{name}</span>
            </li>
          ))}
        </ul>
      </Section>

      <Section title={t("type")}>
        <div className="panels">
          <div className="flex flex-col gap-4 p-6">
            <p className="font-display text-[length:var(--text-display-s)] leading-[1.04] font-medium tracking-[var(--tracking-display)] text-ink">
              {t("displaySample")}
            </p>
            <p className="max-w-[68ch] text-[1.0625rem]">{t("bodySample")}</p>
            <ul className="measured flex flex-wrap gap-x-6 gap-y-1 text-ink">
              <li>192.168.10.0/24</li>
              <li>255.255.255.0</li>
              <li>42 min</li>
              <li>68%</li>
            </ul>
          </div>
        </div>
      </Section>

      <Section title={t("controls")}>
        <div className="panels md:grid-cols-2">
          <div className="flex flex-wrap items-center gap-3 p-6">
            <Button>{t("primary")}</Button>
            <Button variant="secondary">{t("secondary")}</Button>
            <Button variant="ghost">{t("ghost")}</Button>
            <Button variant="danger">{t("danger")}</Button>
            <Button loading>{t("loading")}</Button>
            <Button disabled>{t("disabled")}</Button>
          </div>
          <div className="grid gap-4 p-6 sm:grid-cols-2">
            <Field label={t("inputLabel")} placeholder={t("inputPlaceholder")} type="email" />
            <Field label={t("inputLabel")} defaultValue="learner@" type="email" error={t("inputError")} />
          </div>
        </div>
      </Section>

      <Section title={t("nodeStates")}>
        <div className="grid grid-cols-[repeat(auto-fill,minmax(14rem,1fr))] gap-3">
          <RoadmapNodeCard title="Subnetting" status="locked" statusLabel={t("locked")} />
          <RoadmapNodeCard title="TCP/IP model" status="available" statusLabel={t("available")} />
          <RoadmapNodeCard title="OSI model" status="in_progress" statusLabel={t("inProgress")} percent={40} />
          <RoadmapNodeCard title="Intro to networks" status="completed" statusLabel={t("completed")} />
          <RoadmapNodeCard title="Linux basics" status="available" statusLabel={t("available")} review={{ label: t("review") }} />
        </div>
      </Section>

      <Section title={t("feedback")}>
        <div className="panels md:grid-cols-2">
          <div className="flex items-start gap-3 bg-ok-soft! p-5">
            <CircleCheck aria-hidden className="mt-0.5 size-5 shrink-0 text-ok" />
            <div>
              <p className="font-medium text-ink">{t("correct")}</p>
              <p className="measured text-sm text-ink">255.255.255.192 → /26</p>
            </div>
          </div>
          <div className="flex items-start gap-3 bg-bad-soft! p-5">
            <CircleX aria-hidden className="mt-0.5 size-5 shrink-0 text-bad" />
            <div>
              <p className="font-medium text-ink">{t("incorrect")}</p>
              <p className="measured text-sm text-ink">
                <s>/24</s> /26
              </p>
            </div>
          </div>
        </div>
      </Section>
    </div>
  );
}
