"use client";

import { useTranslations } from "next-intl";
import { useEffect, useRef, useState, type KeyboardEvent } from "react";
import { cn } from "@/lib/utils";
import type { LabActionResult, ShellScenario, ShellState } from "../types";

/**
 * The lab shell's screen. Every command runs on the server (D28); this only draws the
 * transcript and sends what the learner types. The transcript is the attempt's saved history,
 * so it survives a reload; the latest command shows its full output (the saved copy is cut at
 * 4 KB). Up and Down recall earlier commands.
 */
export function Terminal({
  scenario,
  state,
  busy,
  onCommand,
}: {
  scenario: ShellScenario;
  state: ShellState;
  busy: boolean;
  onCommand: (command: string) => Promise<LabActionResult | null>;
}) {
  const t = useTranslations("labs.shell");
  const [line, setLine] = useState("");
  const [recall, setRecall] = useState<number | null>(null);
  const [latest, setLatest] = useState<{ index: number; text: string } | null>(null);
  const input = useRef<HTMLInputElement>(null);
  const screen = useRef<HTMLDivElement>(null);
  const commands = state.history.map((h) => h.command).filter(Boolean);

  useEffect(() => {
    screen.current?.scrollTo?.({ top: screen.current.scrollHeight });
  }, [state.history.length, latest]);

  async function run() {
    const command = line.trim();
    if (!command || busy) return;
    setLine("");
    setRecall(null);
    const result = await onCommand(command);
    if (result?.output.text !== undefined) {
      setLatest({ index: (result.attempt.state as ShellState).history.length - 1, text: result.output.text });
    }
    input.current?.focus();
  }

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === "Enter") {
      e.preventDefault();
      void run();
    } else if (e.key === "ArrowUp" && commands.length) {
      e.preventDefault();
      const next = recall === null ? commands.length - 1 : Math.max(0, recall - 1);
      setRecall(next);
      setLine(commands[next]);
    } else if (e.key === "ArrowDown" && recall !== null) {
      e.preventDefault();
      const next = recall + 1;
      setRecall(next >= commands.length ? null : next);
      setLine(next >= commands.length ? "" : commands[next]);
    }
  }

  return (
    <section aria-label={t("label", { host: scenario.hostname })} className="flex flex-col overflow-hidden rounded-card border lg:flex-1 border-rule bg-paper-3 focus-within:border-accent focus-within:ring-2 focus-within:ring-accent/30">
      <div className="flex items-center justify-between gap-3 border-b border-rule px-4 py-2 text-sm">
        <span className="measured text-ink">
          {scenario.user}@{scenario.hostname}
        </span>
        <span>{t("readOnly")}</span>
      </div>
      <div
        ref={screen}
        onClick={() => input.current?.focus()}
        className="measured h-[26rem] overflow-y-auto px-4 lg:h-auto lg:min-h-[26rem] lg:flex-1 py-3 text-[0.8125rem] leading-relaxed text-ink"
      >
        <p className="text-muted">{t("welcome")}</p>
        <ol role="log" aria-label={t("transcript")} aria-live="polite" className="flex flex-col gap-1">
          {state.history.map((entry, i) => (
            <li key={i} className="flex flex-col">
              <span className="break-all">
                <span aria-hidden className="text-accent">
                  $&nbsp;
                </span>
                {entry.command}
              </span>
              {(latest?.index === i ? latest.text : entry.output) ? (
                <pre className={cn("whitespace-pre-wrap break-all", entry.error && "text-bad")}>{latest?.index === i ? latest.text : entry.output}</pre>
              ) : null}
            </li>
          ))}
        </ol>
        <div className="mt-1 flex items-center gap-2">
          <label htmlFor="lab-command" className="shrink-0 text-accent">
            <span className="sr-only">{t("command")} </span>
            {state.prompt}
          </label>
          <input
            id="lab-command"
            ref={input}
            value={line}
            onChange={(e) => setLine(e.target.value)}
            onKeyDown={onKeyDown}
            maxLength={500}
            autoComplete="off"
            autoCapitalize="off"
            spellCheck={false}
            aria-busy={busy}
            className="min-w-0 flex-1 bg-transparent text-ink caret-[var(--color-accent)] outline-none"
          />
        </div>
      </div>
      <p className="border-t border-rule px-4 py-2 text-sm">{t("commands", { list: scenario.commands.join(", ") })}</p>
    </section>
  );
}
