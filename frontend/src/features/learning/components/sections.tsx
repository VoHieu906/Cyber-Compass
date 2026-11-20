"use client";

import { AlertTriangle, Check, Copy, Info, Lightbulb } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { Diagram } from "../diagrams/registry";
import type { CalloutTone, LessonSection } from "../types";
import { Prose, renderInline } from "./prose";

const calloutStyle: Record<CalloutTone, { box: string; icon: typeof Info; iconClass: string }> = {
  info: { box: "border-rule bg-paper-3", icon: Info, iconClass: "text-muted" },
  tip: {
    box: "border-[color-mix(in_oklab,var(--color-accent)_45%,var(--color-rule))] bg-accent-soft",
    icon: Lightbulb,
    iconClass: "text-accent",
  },
  warning: {
    box: "border-[color-mix(in_oklab,var(--color-attn)_55%,var(--color-rule))] bg-attn-soft",
    icon: AlertTriangle,
    iconClass: "text-attn",
  },
};

function Callout({ heading, text, tone }: { heading: string | null; text: string; tone: CalloutTone }) {
  const t = useTranslations("learn.callout");
  const style = calloutStyle[tone] ?? calloutStyle.info;
  const Icon = style.icon;
  return (
    <aside className={cn("flex gap-3 rounded-control border p-4", style.box)}>
      <Icon aria-hidden className={cn("mt-1 size-4 shrink-0", style.iconClass)} />
      <div className="flex flex-col gap-1">
        <p className="font-medium text-ink">
          <span className="sr-only">{t(tone)}: </span>
          {heading}
        </p>
        <p className="text-ink">{renderInline(text)}</p>
      </div>
    </aside>
  );
}

function Steps({ items }: { items: string[] }) {
  return (
    <ol className="flex flex-col">
      {items.map((item, i) => (
        <li key={i} className="flex gap-4 border-l border-rule-2 pb-4 pl-4 last:pb-0">
          <span className="measured -ml-[1.6rem] flex size-5 shrink-0 items-center justify-center rounded-full border border-rule-2 bg-paper text-xs text-ink">
            {i + 1}
          </span>
          <span className="-mt-0.5">{renderInline(item)}</span>
        </li>
      ))}
    </ol>
  );
}

/** An uploaded lesson image (always WebP, re-encoded by the API). Alt text is required at upload. */
function MediaFigure({
  url,
  alt,
  width,
  height,
  caption,
}: {
  url: string;
  alt: string;
  width: number | null;
  height: number | null;
  caption?: string | null;
}) {
  return (
    <figure className="flex flex-col gap-2">
      {/* eslint-disable-next-line @next/next/no-img-element -- served from the API storage, already sized and re-encoded */}
      <img
        src={url}
        alt={alt}
        width={width ?? undefined}
        height={height ?? undefined}
        loading="lazy"
        className="h-auto w-full rounded-control border border-rule bg-paper-3"
      />
      {caption ? <figcaption className="text-sm">{caption}</figcaption> : null}
    </figure>
  );
}

function CodeBlock({ code, language }: { code: string; language: string }) {
  const t = useTranslations("learn");
  const [copied, setCopied] = useState(false);

  async function copy() {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard can be blocked (permissions, insecure context); the code stays selectable.
    }
  }

  return (
    <div className="overflow-hidden rounded-control border border-rule bg-paper-3">
      <div className="flex items-center justify-between gap-3 border-b border-rule px-3 py-1.5">
        <span className="measured text-xs text-muted">{language}</span>
        <button
          type="button"
          onClick={copy}
          className="inline-flex items-center gap-1.5 rounded-chip px-1.5 py-0.5 text-xs text-muted transition-[color] duration-[var(--dur-micro)] hover:text-ink"
        >
          {copied ? <Check aria-hidden className="size-3.5" /> : <Copy aria-hidden className="size-3.5" />}
          <span aria-live="polite">{copied ? t("copied") : t("copy")}</span>
        </button>
      </div>
      <pre className="overflow-x-auto p-4 text-sm leading-relaxed">
        <code className="measured text-ink">{code}</code>
      </pre>
    </div>
  );
}

export function Section({ section }: { section: LessonSection }) {
  const heading =
    section.heading && section.type !== "callout" ? (
      <h2 className="scroll-mt-[calc(var(--header-height)+1.5rem)] text-[length:var(--text-title)]">
        {section.heading}
      </h2>
    ) : null;

  let body: React.ReactNode = null;
  switch (section.type) {
    case "prose":
      body = <Prose text={section.payload.text} />;
      break;
    case "callout":
      body = <Callout heading={section.heading} text={section.payload.text} tone={section.payload.tone} />;
      break;
    case "steps":
      body = <Steps items={section.payload.items} />;
      break;
    case "code":
      body = <CodeBlock code={section.payload.code} language={section.payload.language} />;
      break;
    case "diagram":
      body = (
        <figure className="flex flex-col gap-3">
          {section.payload.caption ? <figcaption className="text-sm">{section.payload.caption}</figcaption> : null}
          <Diagram diagramKey={section.payload.key} data={section.payload.data} />
        </figure>
      );
      break;
    case "media":
      if (!section.payload.url) return null;
      body = (
        <MediaFigure
          url={section.payload.url}
          alt={section.payload.alt}
          width={section.payload.width}
          height={section.payload.height}
          caption={section.payload.caption}
        />
      );
      break;
    default:
      // Unknown or future types (e.g. visual_3d before its renderer ships) render nothing rather than break the lesson.
      return null;
  }

  return (
    <section id={section.id} className="flex scroll-mt-[calc(var(--header-height)+1.5rem)] flex-col gap-4">
      {heading}
      {body}
    </section>
  );
}
