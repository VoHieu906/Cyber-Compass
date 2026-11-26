"use client";

import { useCallback, useEffect, useId, type ReactNode } from "react";
import { cn } from "@/lib/utils";
import type { PathMessages } from "../types";

/**
 * One text box holding JSON reports to the editor whether its text parses, under a key of its own,
 * and clears it when the box goes away (a removed solution must not leave the editor stuck unsaveable).
 */
export type ValidityReporter = (key: string, invalid: boolean) => void;

export function useValidity(report: ValidityReporter) {
  const key = useId();
  useEffect(() => () => report(key, false), [key, report]);
  return useCallback((invalid: boolean) => report(key, invalid), [key, report]);
}

/** A labelled field with the messages the server (or the schema mirror) gave for it. */
export function Field({
  label,
  messages,
  hint,
  className,
  children,
}: {
  label: string;
  messages?: string[];
  hint?: string;
  className?: string;
  children: (props: { id: string; invalid: boolean; describedBy?: string }) => ReactNode;
}) {
  const id = useId();
  const invalid = Boolean(messages?.length);
  const hintId = `${id}-hint`;
  const errorId = `${id}-error`;
  return (
    <div className={cn("flex flex-col gap-1.5", className)}>
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      {children({
        id,
        invalid,
        describedBy: [hint ? hintId : null, invalid ? errorId : null].filter(Boolean).join(" ") || undefined,
      })}
      {hint ? (
        <p id={hintId} className="text-xs text-muted">
          {hint}
        </p>
      ) : null}
      {invalid ? (
        <p id={errorId} role="alert" className="text-sm text-bad">
          {messages!.join(" ")}
        </p>
      ) : null}
    </div>
  );
}

/** Messages by path under a part of the definition: `scenario.checkpoints.0.path: Use…`. Never the values themselves. */
export function PathList({
  messages,
  tone = "error",
  label,
}: {
  messages: PathMessages;
  tone?: "error" | "warning";
  label: string;
}) {
  const entries = Object.entries(messages).flatMap(([path, list]) => list.map((m) => ({ path, m })));
  if (entries.length === 0) return null;
  return (
    <div
      role={tone === "error" ? "alert" : "status"}
      className={cn(
        "flex flex-col gap-1.5 rounded-control border px-3.5 py-3 text-sm text-ink",
        tone === "error"
          ? "border-[color-mix(in_oklab,var(--color-bad)_45%,var(--color-rule))] bg-bad-soft"
          : "border-[color-mix(in_oklab,var(--color-attn)_45%,var(--color-rule))] bg-attn-soft",
      )}
    >
      <p className="font-medium">{label}</p>
      <ul className="flex flex-col gap-1">
        {entries.map(({ path, m }, i) => (
          <li key={`${path}-${i}`} className="min-w-0 break-words">
            <code className="text-xs">{path}</code>: {m}
          </li>
        ))}
      </ul>
    </div>
  );
}
