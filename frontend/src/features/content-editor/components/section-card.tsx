"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { errorAt, errorsUnder, parseJsonObject, toJsonText, type FieldErrors } from "../editor-state";
import type { BlockTypeInfo, EditorSection } from "../types";
import { RowControls, StringListEditor } from "./list-editors";
import { MediaPicker } from "./media-picker";

type Props = {
  section: EditorSection;
  index: number;
  count: number;
  blockType: BlockTypeInfo | undefined;
  errors: FieldErrors;
  onChange: (section: EditorSection) => void;
  onMove: (to: number) => void;
  onRemove: () => void;
  /** Reports whether this block's JSON text currently fails to parse (saving is blocked meanwhile). */
  onJsonValidity: (invalid: boolean) => void;
};

/**
 * One lesson block in the editor. Flat block types get a form; graph-shaped ones (bespoke
 * diagrams, and the Part C visual blocks) get a JSON editor checked against the server's
 * JSON Schema on save. Server messages appear next to the field they name.
 */
export function SectionCard({
  section,
  index,
  count,
  blockType,
  errors,
  onChange,
  onMove,
  onRemove,
  onJsonValidity,
}: Props) {
  const t = useTranslations("content.editor");
  const headingId = useId();
  const at = `sections.${index}`;
  const payload = section.payload;
  const setPayload = (patch: Record<string, unknown>) => onChange({ ...section, payload: { ...payload, ...patch } });
  const label = blockType?.label ?? section.type;
  const headingError = errorAt(errors, `${at}.heading`);
  const blockErrors = errorsUnder(errors, at);
  const hasErrors = Object.keys(blockErrors).length > 0;

  return (
    <li
      id={`block-${index}`}
      className={cn(
        "panel flex scroll-mt-[calc(var(--header-height)+5rem)] flex-col gap-4 p-4 sm:p-5",
        hasErrors && "border-bad",
      )}
      aria-label={t("blockLabel", { index: index + 1, type: label })}
    >
      <div className="flex items-center justify-between gap-3">
        <p className="flex min-w-0 items-baseline gap-2.5">
          <span className="measured text-xs text-muted">{String(index + 1).padStart(2, "0")}</span>
          <span className="truncate text-sm font-medium text-ink">{label}</span>
        </p>
        <RowControls
          index={index}
          count={count}
          label={t("blockLabel", { index: index + 1, type: label })}
          onMove={onMove}
          onRemove={onRemove}
        />
      </div>

      {errorAt(errors, `${at}.type`) ? <p className="text-sm text-bad">{errorAt(errors, `${at}.type`)}</p> : null}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={headingId} className="text-sm font-medium text-ink">
          {section.type === "callout" ? t("calloutTitle") : t("heading")}
          <span className="font-normal text-muted"> ({t("optional")})</span>
        </label>
        <input
          id={headingId}
          value={section.heading ?? ""}
          maxLength={160}
          onChange={(e) => onChange({ ...section, heading: e.target.value === "" ? null : e.target.value })}
          aria-invalid={Boolean(headingError)}
          className={controlClasses(Boolean(headingError))}
        />
        {headingError ? <p className="text-sm text-bad">{headingError}</p> : null}
      </div>

      <Body
        section={section}
        at={at}
        blockType={blockType}
        errors={errors}
        setPayload={setPayload}
        replacePayload={(p) => onChange({ ...section, payload: p })}
        onJsonValidity={onJsonValidity}
      />
    </li>
  );
}

function Body({
  section,
  at,
  blockType,
  errors,
  setPayload,
  replacePayload,
  onJsonValidity,
}: {
  section: EditorSection;
  at: string;
  blockType: BlockTypeInfo | undefined;
  errors: FieldErrors;
  setPayload: (patch: Record<string, unknown>) => void;
  replacePayload: (payload: Record<string, unknown>) => void;
  onJsonValidity: (invalid: boolean) => void;
}) {
  const t = useTranslations("content.editor");
  const p = section.payload;
  const err = (field: string) => errorAt(errors, `${at}.payload.${field}`);
  const payloadError = errorAt(errors, `${at}.payload`);

  switch (section.type) {
    case "prose":
      return (
        <TextArea
          label={t("text")}
          value={String(p.text ?? "")}
          rows={8}
          error={err("text")}
          hint={t("proseHint")}
          onChange={(v) => setPayload({ text: v })}
        />
      );
    case "callout":
      return (
        <>
          <SelectRow
            label={t("tone")}
            value={String(p.tone ?? "info")}
            options={(["info", "tip", "warning"] as const).map((v) => ({ value: v, label: t(`tones.${v}`) }))}
            error={err("tone")}
            onChange={(v) => setPayload({ tone: v })}
          />
          <TextArea
            label={t("text")}
            value={String(p.text ?? "")}
            rows={3}
            error={err("text")}
            hint={t("inlineHint")}
            onChange={(v) => setPayload({ text: v })}
          />
        </>
      );
    case "steps":
      return (
        <StringListEditor
          label={t("steps")}
          items={Array.isArray(p.items) ? (p.items as string[]) : []}
          onChange={(items) => setPayload({ items })}
          errors={errors}
          errorPrefix={`${at}.payload.items`}
          maxItems={20}
          multiline
          addLabel={t("addStep")}
        />
      );
    case "code":
      return (
        <>
          <InputRow
            label={t("language")}
            value={String(p.language ?? "")}
            error={err("language")}
            hint={t("languageHint")}
            measured
            onChange={(v) => setPayload({ language: v })}
          />
          <TextArea
            label={t("code")}
            value={String(p.code ?? "")}
            rows={8}
            error={err("code")}
            measured
            onChange={(v) => setPayload({ code: v })}
          />
        </>
      );
    case "media":
      return (
        <>
          <MediaPicker value={String(p.media ?? "")} error={err("media")} onChange={(media) => setPayload({ media })} />
          <InputRow
            label={t("caption")}
            optional
            value={String(p.caption ?? "")}
            error={err("caption")}
            onChange={(v) => setPayload({ caption: v === "" ? null : v })}
          />
        </>
      );
    case "diagram":
      return (
        <>
          <SelectRow
            label={t("diagramKey")}
            value={String(p.key ?? "")}
            options={(blockType?.diagram_keys ?? []).map((k) => ({ value: k, label: k }))}
            error={err("key")}
            onChange={(v) => setPayload({ key: v })}
          />
          <InputRow
            label={t("caption")}
            optional
            value={String(p.caption ?? "")}
            error={err("caption")}
            onChange={(v) => setPayload({ caption: v === "" ? null : v })}
          />
          <JsonField
            label={t("diagramData")}
            value={p.data ?? {}}
            errors={errorsUnder(errors, `${at}.payload.data`)}
            onChange={(data) => setPayload({ data })}
            onValidity={onJsonValidity}
          />
        </>
      );
    default:
      // Graph-shaped and future types: the whole payload as JSON, validated by the server's schema.
      return (
        <>
          <JsonField
            label={t("payload")}
            value={p}
            errors={errorsUnder(errors, `${at}.payload`)}
            onChange={replacePayload}
            onValidity={onJsonValidity}
          />
          {payloadError ? <p className="text-sm text-bad">{payloadError}</p> : null}
        </>
      );
  }
}

/**
 * JSON text for one value. Valid text updates the block at once; invalid text stays in the
 * box with its parse error, and the editor refuses to save until it parses.
 */
function JsonField({
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

function TextArea({
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

function InputRow({
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

function SelectRow({
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
