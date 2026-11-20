"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { parseJsonObject, toJsonText, type FieldErrors } from "../editor-state";

/* Field building blocks shared by the block editors (section-card.tsx, scene-editors.tsx). */

/**
 * JSON text for one value. Valid text updates the block at once; invalid text stays in the
 * box with its parse error, and the editor refuses to save until it parses.
 */
export function JsonField({
  label,
  value,
  errors,
  onChange,
  onValidity,
}: {
  label: string;
  value: unknown;
  errors: FieldErrors;
  onChange: (value: Record<string, unknown>) => void;
  onValidity: (invalid: boolean) => void;
}) {
  const t = useTranslations("content.editor");
  const id = useId();
  const [text, setText] = useState(() => toJsonText(value));
  const [parseError, setParseError] = useState<string | null>(null);

  // Restoring a revision or discarding a draft replaces the value from outside.
  const external = toJsonText(value);
  const [lastExternal, setLastExternal] = useState(external);
  if (external !== lastExternal) {
    setLastExternal(external);
    if (!parseError) setText(external);
  }

  const serverMessages = Object.entries(errors);
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      <textarea
        id={id}
        value={text}
        spellCheck={false}
        rows={Math.min(18, Math.max(6, text.split("\n").length + 1))}
        onChange={(e) => {
          setText(e.target.value);
          const parsed = parseJsonObject(e.target.value);
          if (parsed.ok) {
            setParseError(null);
            onValidity(false);
            setLastExternal(toJsonText(parsed.value));
            onChange(parsed.value);
          } else {
            setParseError(parsed.error === "object" ? t("jsonNotObject") : t("jsonSyntax", { message: parsed.error }));
            onValidity(true);
          }
        }}
        aria-invalid={Boolean(parseError) || serverMessages.length > 0}
        className={controlClasses(
          Boolean(parseError) || serverMessages.length > 0,
          "measured h-auto py-2 text-sm leading-relaxed",
        )}
      />
      {parseError ? (
        <p className="text-sm text-bad">{parseError}</p>
      ) : (
        <p className="text-sm text-muted">{t("jsonHint")}</p>
      )}
      {serverMessages.length > 0 ? (
        <ul className="flex flex-col gap-0.5 text-sm text-bad">
          {serverMessages.map(([path, messages]) => (
            <li key={path}>
              {path ? <code className="measured text-xs">{path}</code> : null} {messages[0]}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}

export function TextArea({
  label,
  value,
  rows,
  error,
  hint,
  measured,
  onChange,
}: {
  label: string;
  value: string;
  rows: number;
  error?: string;
  hint?: string;
  measured?: boolean;
  onChange: (value: string) => void;
}) {
  const id = useId();
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      <textarea
        id={id}
        value={value}
        rows={rows}
        spellCheck={!measured}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={Boolean(error)}
        aria-describedby={error || hint ? `${id}-msg` : undefined}
        className={controlClasses(Boolean(error), cn("h-auto py-2 leading-relaxed", measured && "measured text-sm"))}
      />
      {error || hint ? (
        <p id={`${id}-msg`} className={cn("text-sm", error ? "text-bad" : "text-muted")}>
          {error ?? hint}
        </p>
      ) : null}
    </div>
  );
}

export function InputRow({
  label,
  value,
  error,
  hint,
  optional,
  measured,
  onChange,
}: {
  label: string;
  value: string;
  error?: string;
  hint?: string;
  optional?: boolean;
  measured?: boolean;
  onChange: (value: string) => void;
}) {
  const t = useTranslations("content.editor");
  const id = useId();
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
        {optional ? <span className="font-normal text-muted"> ({t("optional")})</span> : null}
      </label>
      <input
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={Boolean(error)}
        className={controlClasses(Boolean(error), cn(measured && "measured max-w-[14rem] text-sm"))}
      />
      {error || hint ? <p className={cn("text-sm", error ? "text-bad" : "text-muted")}>{error ?? hint}</p> : null}
    </div>
  );
}

export function SelectRow({
  label,
  value,
  options,
  error,
  onChange,
}: {
  label: string;
  value: string;
  options: { value: string; label: string }[];
  error?: string;
  onChange: (value: string) => void;
}) {
  const id = useId();
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      <select
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={Boolean(error)}
        className={controlClasses(Boolean(error), "sm:max-w-[16rem]")}
      >
        {options.some((o) => o.value === value) ? null : <option value={value}>{value || "—"}</option>}
        {options.map((o) => (
          <option key={o.value} value={o.value}>
            {o.label}
          </option>
        ))}
      </select>
      {error ? <p className="text-sm text-bad">{error}</p> : null}
    </div>
  );
}
