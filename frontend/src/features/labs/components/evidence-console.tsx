"use client";

import { Check, TriangleAlert } from "lucide-react";
import { useTranslations } from "next-intl";
import { useMemo, useRef, useState, type KeyboardEvent } from "react";
import { SegmentedControl } from "@/components/ui/segmented-control";
import { cn } from "@/lib/utils";
import type { EvidenceScenario, EvidenceState, LabAction, LabActionResult } from "../types";

type Artifact = EvidenceScenario["artifacts"][number];

/**
 * The evidence of an evidence-review lab (D80): each artifact is plain text lines the learner marks.
 * Every line is a text node, so what an artifact holds (HTML, a script URL, an image tag) shows as
 * the characters it is and never runs, loads or links (ARCHITECTURE §7.1). Marking works from the
 * keyboard: arrows move between lines, Space or Enter marks or unmarks, and each change is announced.
 */
export function EvidenceConsole({
  scenario,
  state,
  busy,
  onAction,
}: {
  scenario: EvidenceScenario;
  state: EvidenceState;
  busy: boolean;
  onAction: (action: LabAction) => Promise<LabActionResult | null>;
}) {
  const t = useTranslations("labs.evidence");
  const marks = useMemo(() => new Set(state.marks), [state.marks]);
  const [announcement, setAnnouncement] = useState("");

  async function toggle(artifact: Artifact, view: Artifact["views"][number], line: number) {
    if (busy) return;
    const on = !marks.has(`${artifact.id}:${view.id}:${line}`);
    const result = await onAction({ type: "mark", artifact: artifact.id, view: view.id, line, on });
    if (!result) return;
    const count = (result.attempt.state as EvidenceState).marks.filter((k) => k.startsWith(`${artifact.id}:`)).length;
    setAnnouncement(
      t(on ? "announceOn" : "announceOff", { line, count, max: artifact.max_marks, artifact: artifact.title }),
    );
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="measured text-sm">{t("howTo")}</p>
      {scenario.artifacts.map((artifact) => (
        <ArtifactPanel key={artifact.id} artifact={artifact} marks={marks} busy={busy} onToggle={toggle} />
      ))}
      <p role="status" aria-live="polite" className="sr-only">
        {announcement}
      </p>
    </div>
  );
}

function ArtifactPanel({
  artifact,
  marks,
  busy,
  onToggle,
}: {
  artifact: Artifact;
  marks: Set<string>;
  busy: boolean;
  onToggle: (artifact: Artifact, view: Artifact["views"][number], line: number) => void;
}) {
  const t = useTranslations("labs.evidence");
  const [viewId, setViewId] = useState(artifact.views[0].id);
  const view = artifact.views.find((v) => v.id === viewId) ?? artifact.views[0];
  const [active, setActive] = useState(0);
  const refs = useRef<(HTMLLIElement | null)[]>([]);
  const used = [...marks].filter((k) => k.startsWith(`${artifact.id}:`)).length;
  const over = used > artifact.max_marks;
  const headingId = `artifact-${artifact.id}`;

  function move(to: number) {
    const next = Math.min(view.lines.length - 1, Math.max(0, to));
    setActive(next);
    refs.current[next]?.focus();
  }

  function onKeyDown(event: KeyboardEvent<HTMLLIElement>, index: number) {
    if (event.key === "ArrowDown") move(index + 1);
    else if (event.key === "ArrowUp") move(index - 1);
    else if (event.key === "Home") move(0);
    else if (event.key === "End") move(view.lines.length - 1);
    else if (event.key === " " || event.key === "Enter") onToggle(artifact, view, index + 1);
    else return;
    event.preventDefault();
  }

  return (
    <section aria-labelledby={headingId} className="panel flex flex-col gap-3 p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h3 id={headingId} className="text-[length:var(--text-title)]">
          {artifact.title}
        </h3>
        {artifact.views.length > 1 ? (
          <SegmentedControl
            label={t("views", { artifact: artifact.title })}
            value={view.id}
            options={artifact.views.map((v) => ({ value: v.id, label: v.label }))}
            onChange={(id) => {
              setViewId(id);
              setActive(0);
            }}
          />
        ) : (
          <p className="text-sm text-muted">{view.label}</p>
        )}
      </div>

      <ol
        role="listbox"
        aria-multiselectable="true"
        aria-label={t("lines", { view: view.label, artifact: artifact.title })}
        aria-busy={busy || undefined}
        className="overflow-hidden rounded-control border border-rule bg-paper"
      >
        {view.lines.map((text, i) => {
          const marked = marks.has(`${artifact.id}:${view.id}:${i + 1}`);
          return (
            <li
              key={i}
              ref={(el) => {
                refs.current[i] = el;
              }}
              role="option"
              aria-selected={marked}
              tabIndex={i === active ? 0 : -1}
              onFocus={() => setActive(i)}
              onClick={() => onToggle(artifact, view, i + 1)}
              onKeyDown={(e) => onKeyDown(e, i)}
              className={cn(
                "flex cursor-pointer items-start gap-3 border-l-4 px-3 py-1.5 text-sm text-ink transition-[background-color] duration-[var(--dur-micro)] focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent",
                marked ? "border-accent bg-accent-soft" : "border-transparent hover:bg-paper-2",
                i > 0 && "border-t border-t-rule",
              )}
            >
              <span aria-hidden className="w-7 shrink-0 text-right font-mono text-xs text-muted select-none">
                {i + 1}
              </span>
              <span className="sr-only">{t("lineNumber", { line: i + 1 })}: </span>
              <span
                className={cn("min-w-0 flex-1 break-words whitespace-pre-wrap", view.mono && "font-mono text-[13px]")}
              >
                {text || " "}
              </span>
              {marked ? <Check aria-hidden className="mt-0.5 size-4 shrink-0 text-accent" /> : null}
            </li>
          );
        })}
      </ol>

      <p className={cn("flex items-center gap-2 text-xs", over ? "font-medium text-bad" : "text-muted")}>
        {over ? <TriangleAlert aria-hidden className="size-4 shrink-0" /> : null}
        {over ? t("over", { max: artifact.max_marks }) : t("count", { count: used, max: artifact.max_marks })}
      </p>
    </section>
  );
}
