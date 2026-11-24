"use client";

import { AlertTriangle, Check, Copy, ExternalLink, Info, Lightbulb, Link2, Puzzle, Repeat2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { cn } from "@/lib/utils";
import { SceneFrame } from "../blocks/scene-frame";
import { isVisualBlock, VisualBlock } from "../blocks/visual-block";
import { Diagram } from "../diagrams/registry";
import type { CalloutTone, LessonSection, SectionSource } from "../types";
import { LazyMount } from "./lazy-mount";
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
  recap: { box: "border-rule-2 bg-paper", icon: Repeat2, iconClass: "text-ink-2" },
  "fun-fact": {
    box: "border-dashed border-[color-mix(in_oklab,var(--color-accent)_45%,var(--color-rule))] bg-paper",
    icon: Puzzle,
    iconClass: "text-accent",
  },
};

/** Tones whose name is part of what the reader sees ("In other words", "Fun fact"), not only announced. */
const NAMED_TONES: CalloutTone[] = ["recap", "fun-fact"];

function Callout({ heading, text, tone }: { heading: string | null; text: string; tone: CalloutTone }) {
  const t = useTranslations("learn.callout");
  const style = calloutStyle[tone] ?? calloutStyle.info;
  const Icon = style.icon;
  return (
    <aside className={cn("flex gap-3 rounded-control border p-4", style.box)}>
      <Icon aria-hidden className={cn("mt-1 size-4 shrink-0", style.iconClass)} />
      <div className="flex flex-col gap-1">
        {NAMED_TONES.includes(tone) ? (
          <p className="font-medium text-ink">
            {heading ? (
              <>
                <span className="text-muted">{t(tone)}: </span>
                {heading}
              </>
            ) : (
              t(tone)
            )}
          </p>
        ) : (
          <p className="font-medium text-ink">
            <span className="sr-only">{t(tone)}: </span>
            {heading}
          </p>
        )}
        <p className="text-ink">{renderInline(text)}</p>
      </div>
    </aside>
  );
}

/** The sources behind a section's facts (D68), collapsed under the section. */
function Sources({ sources }: { sources: SectionSource[] }) {
  const t = useTranslations("learn");
  return (
    <details className="text-sm">
      <summary className="w-fit cursor-pointer rounded-chip text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
        {t("sources", { count: sources.length })}
      </summary>
      <ol className="mt-2 flex list-decimal flex-col gap-1.5 pl-5 marker:text-muted">
        {sources.map((s, i) => (
          <li key={i}>
            <a
              href={s.url}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1 text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
            >
              {s.label}
              <ExternalLink aria-hidden className="size-3" />
              <span className="sr-only">({t("opensInNewTab")})</span>
            </a>
            <span className="text-muted"> · {t(`sourceKinds.${s.kind}`)}</span>
            {s.note ? <span className="text-muted"> · {s.note}</span> : null}
          </li>
        ))}
      </ol>
    </details>
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
      {/* Focusable so a keyboard can scroll a long line sideways (WCAG 2.1.1). */}
      <pre tabIndex={0} className="overflow-x-auto p-4 text-sm leading-relaxed">
        <code className="measured text-ink">{code}</code>
      </pre>
    </div>
  );
}

/** Blocks that lay out a graph or run a calculator: in a long post they mount near the viewport. */
const HEAVY: Record<string, string> = {
  flow: "aspect-[16/10]",
  tree: "aspect-[16/10]",
  topology: "aspect-[16/10]",
  calculator: "min-h-[24rem]",
};

/** Copies the section's address (the page URL with its #id) and puts it in the address bar. */
function CopyLink({ id, heading }: { id: string; heading: string }) {
  const t = useTranslations("learn");
  const [copied, setCopied] = useState(false);

  async function copy() {
    const url = `${window.location.origin}${window.location.pathname}${window.location.search}#${id}`;
    window.history.replaceState(null, "", `#${id}`);
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard blocked: the address bar now holds the link.
    }
  }

  return (
    <button
      type="button"
      onClick={copy}
      aria-label={t("copySectionLink", { heading })}
      className="inline-flex size-7 shrink-0 items-center justify-center rounded-chip text-muted transition-[color,opacity] duration-[var(--dur-micro)] group-hover:opacity-100 hover:text-ink focus-visible:opacity-100 [@media(hover:hover)]:opacity-0"
    >
      {copied ? <Check aria-hidden className="size-4" /> : <Link2 aria-hidden className="size-4" />}
      <span className="sr-only" aria-live="polite">
        {copied ? t("linkCopied") : ""}
      </span>
    </button>
  );
}

export function Section({ section, enhanced = false }: { section: LessonSection; enhanced?: boolean }) {
  const title =
    section.heading && section.type !== "callout" ? (
      <h2 className="scroll-mt-[calc(var(--header-height)+1.5rem)] text-[length:var(--text-title)]">
        {section.heading}
      </h2>
    ) : null;
  // The link button sits beside the heading, not inside it, so the heading's accessible name stays its text.
  const heading =
    title && enhanced ? (
      <div className="group flex items-center gap-1">
        {title}
        <CopyLink id={section.id} heading={section.heading!} />
      </div>
    ) : (
      title
    );

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
    case "scene-3d":
    case "script-3d":
      body = <SceneFrame kind={section.type} payload={section.payload} />;
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
      // The generic visual blocks validate their own payloads. Unknown or future types render
      // nothing rather than break the lesson.
      if (!isVisualBlock(section.type)) return null;
      body = <VisualBlock type={section.type} payload={section.payload} />;
      if (enhanced && HEAVY[section.type]) body = <LazyMount className={HEAVY[section.type]}>{body}</LazyMount>;
  }

  return (
    // A container, so blocks lay themselves out by the width they get: a full lesson column, the
    // exercise theory panel or the editor preview (container queries, not viewport breakpoints).
    <section id={section.id} className="@container flex scroll-mt-[calc(var(--header-height)+1.5rem)] flex-col gap-4">
      {heading}
      {body}
      {section.sources?.length ? <Sources sources={section.sources} /> : null}
    </section>
  );
}
