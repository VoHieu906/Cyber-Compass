"use client";

import { Plus } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useState } from "react";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { blockSchemas, CALCULATOR_TOOLS } from "@/features/learning/blocks/schemas";
import { VisualBlock } from "@/features/learning/blocks/visual-block";
import { describeIssues } from "@/sandbox/scene3d/schema";
import { errorAt, errorsUnder, moveItem, type FieldErrors } from "../editor-state";
import type { BlockTypeInfo } from "../types";
import { InputRow, JsonField, SelectRow, TextArea } from "./fields";
import { RowControls } from "./list-editors";
import { ClientIssues } from "./scene-editors";

/*
 * Editors for the generic 2D blocks. Each shows the learner's own renderer as a live preview,
 * fed with the unsaved payload once it passes the shared zod contract; until then the issues
 * are listed with their paths. The server re-validates everything on save.
 */

type Payload = Record<string, unknown>;
type EditorProps = {
  type: string;
  at: string;
  payload: Payload;
  errors: FieldErrors;
  replacePayload: (payload: Payload) => void;
};

function useClientIssues(type: string, payload: Payload) {
  const schema = blockSchemas[type as keyof typeof blockSchemas];
  const result = schema?.safeParse(payload);
  return { valid: Boolean(result?.success), issues: result && !result.success ? describeIssues(result.error) : [] };
}

/** The learner renderer on the latest valid payload; it keeps the last good one while you type. */
export function VisualPreview({ type, payload, valid }: { type: string; payload: Payload; valid: boolean }) {
  const t = useTranslations("content.visual");
  const serialized = JSON.stringify(payload);
  const [shown, setShown] = useState<string | null>(valid ? serialized : null);

  useEffect(() => {
    if (!valid) return;
    const timer = setTimeout(() => setShown(serialized), 250);
    return () => clearTimeout(timer);
  }, [serialized, valid]);

  return (
    <div className="flex min-w-0 flex-col gap-2">
      <p className="text-sm font-medium text-ink">{t("livePreview")}</p>
      {shown ? (
        <div className="@container rounded-control border border-dashed border-rule-2 p-3 sm:p-4">
          <VisualBlock type={type} payload={JSON.parse(shown)} />
        </div>
      ) : (
        <p className="rounded-control border border-dashed border-rule-2 p-6 text-center text-sm">
          {t("previewWaiting")}
        </p>
      )}
    </div>
  );
}

function Layout({
  type,
  payload,
  valid,
  children,
}: {
  type: string;
  payload: Payload;
  valid: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className="grid gap-5 xl:grid-cols-2">
      <div className="flex min-w-0 flex-col gap-4">{children}</div>
      <VisualPreview type={type} payload={payload} valid={valid} />
    </div>
  );
}

// ---------- JSON-edited blocks ----------

type SchemaNode = {
  type?: string | string[];
  properties?: Record<string, SchemaNode>;
  required?: string[];
  items?: SchemaNode;
  enum?: unknown[];
  maxItems?: number;
  minItems?: number;
  maxLength?: number;
  minimum?: number;
  maximum?: number;
  $ref?: string;
  pattern?: string;
};

function describe(node: SchemaNode, defs: Record<string, SchemaNode>): string {
  const resolved = node.$ref ? (defs[node.$ref.split("/").pop()!] ?? node) : node;
  if (resolved.enum) return resolved.enum.join(" | ");
  if (resolved.pattern === "^[a-z0-9][a-z0-9-]{0,39}$") return "id (a-z, 0-9, -)";
  const type = Array.isArray(resolved.type) ? resolved.type.join(" | ") : (resolved.type ?? "");
  if (type === "array") {
    const bounds = `${resolved.minItems ?? 0}–${resolved.maxItems ?? "∞"}`;
    return `list of ${resolved.items?.properties ? "objects" : describe(resolved.items ?? {}, defs)} (${bounds})`;
  }
  if (type === "string" && resolved.maxLength) return `text ≤ ${resolved.maxLength}`;
  if (type === "number" && resolved.maximum !== undefined)
    return `number ${resolved.minimum ?? ""}–${resolved.maximum}`;
  return type;
}

/** Fields of the block, read from the JSON Schema the API serves, two levels deep. */
function SchemaReference({ schema }: { schema: Record<string, unknown> }) {
  const t = useTranslations("content.visual");
  const root = schema as SchemaNode & { $defs?: Record<string, SchemaNode> };
  const defs = root.$defs ?? {};
  const rows: { path: string; required: boolean; description: string }[] = [];
  const walk = (node: SchemaNode, prefix: string, depth: number) => {
    for (const [key, child] of Object.entries(node.properties ?? {})) {
      const path = prefix ? `${prefix}.${key}` : key;
      rows.push({ path, required: (node.required ?? []).includes(key), description: describe(child, defs) });
      const inner = child.items?.properties ? child.items : child.properties ? child : null;
      if (inner && depth < 2) walk(inner, child.items ? `${path}[]` : path, depth + 1);
    }
  };
  walk(root, "", 0);

  return (
    <details className="rounded-control border border-rule px-3 py-2 text-sm">
      <summary className="cursor-pointer rounded-chip font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
        {t("fieldReference")}
      </summary>
      <p className="mt-2 text-muted">{t("fieldReferenceHint")}</p>
      <dl className="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[auto_1fr]">
        {rows.map((r) => (
          <div key={r.path} className="contents">
            <dt className="measured text-xs text-ink">
              {r.path}
              {r.required ? <span className="text-attn"> *</span> : null}
            </dt>
            <dd className="text-xs">{r.description}</dd>
          </div>
        ))}
      </dl>
    </details>
  );
}

export function JsonVisualEditor({
  type,
  at,
  payload,
  errors,
  replacePayload,
  blockType,
  onJsonValidity,
}: EditorProps & { blockType: BlockTypeInfo | undefined; onJsonValidity: (invalid: boolean) => void }) {
  const t = useTranslations("content.editor");
  const { valid, issues } = useClientIssues(type, payload);
  return (
    <Layout type={type} payload={payload} valid={valid}>
      <JsonField
        label={t("payload")}
        value={payload}
        errors={errorsUnder(errors, `${at}.payload`)}
        onChange={replacePayload}
        onValidity={onJsonValidity}
      />
      <ClientIssues issues={issues} />
      {blockType ? <SchemaReference schema={blockType.schema} /> : null}
    </Layout>
  );
}

// ---------- Form-edited blocks ----------

function AddButton({ label, onClick }: { label: string; onClick: () => void }) {
  return (
    <div>
      <Button variant="ghost" size="sm" onClick={onClick}>
        <Plus aria-hidden />
        {label}
      </Button>
    </div>
  );
}

function ListError({ errors, path }: { errors: FieldErrors; path: string }) {
  const message = errorAt(errors, path);
  return message ? <p className="text-sm text-bad">{message}</p> : null;
}

type Comparison = { columns: string[]; rows: { label: string; values: string[] }[] };

export function ComparisonEditor({ type, at, payload, errors, replacePayload }: EditorProps) {
  const t = useTranslations("content.visual");
  const { valid, issues } = useClientIssues(type, payload);
  const data: Comparison = {
    columns: Array.isArray(payload.columns) ? (payload.columns as string[]) : [],
    rows: Array.isArray(payload.rows) ? (payload.rows as Comparison["rows"]) : [],
  };
  const p = `${at}.payload`;
  const set = (next: Comparison) => replacePayload({ ...payload, ...next });
  const setColumn = (c: number, value: string) =>
    set({ ...data, columns: data.columns.map((x, i) => (i === c ? value : x)) });
  const addColumn = () =>
    set({ columns: [...data.columns, ""], rows: data.rows.map((r) => ({ ...r, values: [...r.values, ""] })) });
  const removeColumn = (c: number) =>
    set({
      columns: data.columns.filter((_, i) => i !== c),
      rows: data.rows.map((r) => ({ ...r, values: r.values.filter((_, i) => i !== c) })),
    });
  const moveColumn = (from: number, to: number) =>
    set({
      columns: moveItem(data.columns, from, to),
      rows: data.rows.map((r) => ({ ...r, values: moveItem(r.values, from, to) })),
    });
  const setRow = (r: number, patch: Partial<Comparison["rows"][number]>) =>
    set({ ...data, rows: data.rows.map((row, i) => (i === r ? { ...row, ...patch } : row)) });

  return (
    <Layout type={type} payload={payload} valid={valid}>
      <fieldset className="flex min-w-0 flex-col gap-2">
        <legend className="mb-1.5 text-sm font-medium text-ink">{t("columns")}</legend>
        <div className="grid gap-2 sm:grid-cols-2">
          {data.columns.map((c, i) => (
            <div key={i} className="flex items-start gap-1">
              <input
                value={c}
                onChange={(e) => setColumn(i, e.target.value)}
                aria-label={t("columnN", { n: i + 1 })}
                aria-invalid={Boolean(errorAt(errors, `${p}.columns.${i}`))}
                className={controlClasses(Boolean(errorAt(errors, `${p}.columns.${i}`)))}
              />
              <RowControls
                index={i}
                count={data.columns.length}
                label={t("columnN", { n: i + 1 })}
                onMove={(to) => moveColumn(i, to)}
                onRemove={data.columns.length > 2 ? () => removeColumn(i) : undefined}
              />
            </div>
          ))}
        </div>
        <ListError errors={errors} path={`${p}.columns`} />
        {data.columns.length < 4 ? <AddButton label={t("addColumn")} onClick={addColumn} /> : null}
      </fieldset>

      <fieldset className="flex min-w-0 flex-col gap-3">
        <legend className="mb-1.5 text-sm font-medium text-ink">{t("rows")}</legend>
        {data.rows.map((row, r) => (
          <div key={r} className="flex flex-col gap-2 rounded-control border border-rule p-3">
            <div className="flex items-start gap-2">
              <input
                value={row.label}
                onChange={(e) => setRow(r, { label: e.target.value })}
                placeholder={t("rowLabel")}
                aria-label={t("rowN", { n: r + 1 })}
                aria-invalid={Boolean(errorAt(errors, `${p}.rows.${r}.label`))}
                className={controlClasses(Boolean(errorAt(errors, `${p}.rows.${r}.label`)), "font-medium")}
              />
              <RowControls
                index={r}
                count={data.rows.length}
                label={t("rowN", { n: r + 1 })}
                onMove={(to) => set({ ...data, rows: moveItem(data.rows, r, to) })}
                onRemove={() => set({ ...data, rows: data.rows.filter((_, i) => i !== r) })}
              />
            </div>
            <div className="grid gap-2 sm:grid-cols-2">
              {data.columns.map((c, ci) => (
                <textarea
                  key={ci}
                  rows={2}
                  value={row.values[ci] ?? ""}
                  onChange={(e) => {
                    const values = data.columns.map((_, i) => (i === ci ? e.target.value : (row.values[i] ?? "")));
                    setRow(r, { values });
                  }}
                  aria-label={`${row.label || t("rowN", { n: r + 1 })}: ${c || t("columnN", { n: ci + 1 })}`}
                  placeholder={c}
                  className={controlClasses(Boolean(errorAt(errors, `${p}.rows.${r}.values.${ci}`)), "h-auto py-2")}
                />
              ))}
            </div>
            <ListError errors={errors} path={`${p}.rows.${r}.values`} />
          </div>
        ))}
        <ListError errors={errors} path={`${p}.rows`} />
        {data.rows.length < 15 ? (
          <AddButton
            label={t("addRow")}
            onClick={() => set({ ...data, rows: [...data.rows, { label: "", values: data.columns.map(() => "") }] })}
          />
        ) : null}
      </fieldset>
      <ClientIssues issues={issues} />
    </Layout>
  );
}

type TimelineEvent = { when: string; title: string; detail?: string };

export function TimelineEditor({ type, at, payload, errors, replacePayload }: EditorProps) {
  const t = useTranslations("content.visual");
  const { valid, issues } = useClientIssues(type, payload);
  const events = Array.isArray(payload.events) ? (payload.events as TimelineEvent[]) : [];
  const p = `${at}.payload.events`;
  const set = (next: TimelineEvent[]) => replacePayload({ ...payload, events: next });
  const update = (i: number, patch: Partial<TimelineEvent>) =>
    set(
      events.map((e, j) => {
        if (j !== i) return e;
        const next = { ...e, ...patch };
        if (!next.detail) delete next.detail;
        return next;
      }),
    );

  return (
    <Layout type={type} payload={payload} valid={valid}>
      <fieldset className="flex min-w-0 flex-col gap-3">
        <legend className="mb-1.5 text-sm font-medium text-ink">{t("events")}</legend>
        {events.map((e, i) => (
          <div key={i} className="flex flex-col gap-2 rounded-control border border-rule p-3">
            <div className="flex items-start gap-2">
              <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-[9rem_1fr]">
                <input
                  value={e.when}
                  onChange={(ev) => update(i, { when: ev.target.value })}
                  placeholder={t("when")}
                  aria-label={`${t("when")} ${i + 1}`}
                  aria-invalid={Boolean(errorAt(errors, `${p}.${i}.when`))}
                  className={controlClasses(Boolean(errorAt(errors, `${p}.${i}.when`)), "measured text-sm")}
                />
                <input
                  value={e.title}
                  onChange={(ev) => update(i, { title: ev.target.value })}
                  placeholder={t("eventTitle")}
                  aria-label={`${t("eventTitle")} ${i + 1}`}
                  aria-invalid={Boolean(errorAt(errors, `${p}.${i}.title`))}
                  className={controlClasses(Boolean(errorAt(errors, `${p}.${i}.title`)))}
                />
              </div>
              <RowControls
                index={i}
                count={events.length}
                label={t("eventN", { n: i + 1 })}
                onMove={(to) => set(moveItem(events, i, to))}
                onRemove={() => set(events.filter((_, j) => j !== i))}
              />
            </div>
            <textarea
              rows={2}
              value={e.detail ?? ""}
              onChange={(ev) => update(i, { detail: ev.target.value })}
              placeholder={t("detailOptional")}
              aria-label={`${t("detail")} ${i + 1}`}
              className={controlClasses(Boolean(errorAt(errors, `${p}.${i}.detail`)), "h-auto py-2")}
            />
            <ListError errors={errors} path={`${p}.${i}.when`} />
            <ListError errors={errors} path={`${p}.${i}.title`} />
          </div>
        ))}
        <ListError errors={errors} path={p} />
        {events.length < 20 ? (
          <AddButton label={t("addEvent")} onClick={() => set([...events, { when: "", title: "" }])} />
        ) : null}
      </fieldset>
      <ClientIssues issues={issues} />
    </Layout>
  );
}

export function CalculatorEditor({ type, at, payload, errors, replacePayload }: EditorProps) {
  const t = useTranslations("content.visual");
  const { valid } = useClientIssues(type, payload);
  const tool = String(payload.tool ?? "cidr");
  return (
    <Layout type={type} payload={payload} valid={valid}>
      <SelectRow
        label={t("tool")}
        value={tool}
        options={CALCULATOR_TOOLS.map((v) => ({ value: v, label: t(`tools.${v}`) }))}
        error={errorAt(errors, `${at}.payload.tool`)}
        onChange={(v) => {
          const { initial: _drop, ...rest } = payload;
          void _drop;
          replacePayload({ ...rest, tool: v });
        }}
      />
      <InputRow
        label={t("initial")}
        optional
        measured
        value={String(payload.initial ?? "")}
        error={errorAt(errors, `${at}.payload.initial`) ?? (valid ? undefined : t("initialInvalid"))}
        hint={t(`initialHint.${tool}`)}
        onChange={(v) => {
          const { initial: _drop, ...rest } = payload;
          void _drop;
          replacePayload(v === "" ? rest : { ...rest, initial: v });
        }}
      />
    </Layout>
  );
}

type Option = { text: string; correct: boolean };
type Question = { prompt: string; options: Option[]; explanation: string };

export function KnowledgeCheckEditor({ type, at, payload, errors, replacePayload }: EditorProps) {
  const t = useTranslations("content.visual");
  const { valid, issues } = useClientIssues(type, payload);
  const questions = Array.isArray(payload.questions) ? (payload.questions as Question[]) : [];
  const p = `${at}.payload.questions`;
  const set = (next: Question[]) => replacePayload({ ...payload, questions: next });
  const update = (q: number, patch: Partial<Question>) =>
    set(questions.map((x, i) => (i === q ? { ...x, ...patch } : x)));
  const blank = (): Question => ({
    prompt: "",
    options: [
      { text: "", correct: true },
      { text: "", correct: false },
    ],
    explanation: "",
  });

  return (
    <Layout type={type} payload={payload} valid={valid}>
      <p className="text-sm text-muted">{t("ungradedNote")}</p>
      {questions.map((q, qi) => (
        <QuestionEditor
          key={qi}
          index={qi}
          count={questions.length}
          question={q}
          errors={errors}
          prefix={`${p}.${qi}`}
          onChange={(patch) => update(qi, patch)}
          onMove={(to) => set(moveItem(questions, qi, to))}
          onRemove={questions.length > 1 ? () => set(questions.filter((_, i) => i !== qi)) : undefined}
        />
      ))}
      <ListError errors={errors} path={p} />
      {questions.length < 3 ? (
        <AddButton label={t("addQuestion")} onClick={() => set([...questions, blank()])} />
      ) : null}
      <ClientIssues issues={issues} />
    </Layout>
  );
}

function QuestionEditor({
  index,
  count,
  question,
  errors,
  prefix,
  onChange,
  onMove,
  onRemove,
}: {
  index: number;
  count: number;
  question: Question;
  errors: FieldErrors;
  prefix: string;
  onChange: (patch: Partial<Question>) => void;
  onMove: (to: number) => void;
  onRemove?: () => void;
}) {
  const t = useTranslations("content.visual");
  const groupId = useId();
  const options = question.options ?? [];
  const setOption = (o: number, patch: Partial<Option>) =>
    onChange({ options: options.map((x, i) => (i === o ? { ...x, ...patch } : x)) });

  return (
    <fieldset className="flex min-w-0 flex-col gap-3 rounded-control border border-rule p-3">
      <legend className="sr-only">{t("questionN", { n: index + 1 })}</legend>
      <div className="flex items-center justify-between gap-2">
        <p aria-hidden className="text-sm font-medium text-ink">
          {t("questionN", { n: index + 1 })}
        </p>
        <RowControls
          index={index}
          count={count}
          label={t("questionN", { n: index + 1 })}
          onMove={onMove}
          onRemove={onRemove}
        />
      </div>
      <TextArea
        label={t("prompt")}
        value={question.prompt ?? ""}
        rows={2}
        error={errorAt(errors, `${prefix}.prompt`)}
        onChange={(v) => onChange({ prompt: v })}
      />
      <div className="flex flex-col gap-2">
        <p id={`${groupId}-options`} className="text-sm font-medium text-ink">
          {t("options")}
        </p>
        <p className="text-sm text-muted">{t("optionsHint")}</p>
        <ul className="flex flex-col gap-2" aria-labelledby={`${groupId}-options`}>
          {options.map((o, oi) => (
            <li key={oi} className="flex items-start gap-2">
              <label className="mt-2.5 flex shrink-0 items-center gap-1.5 text-xs text-muted">
                <input
                  type="checkbox"
                  checked={o.correct}
                  onChange={(e) => setOption(oi, { correct: e.target.checked })}
                  className="accent-[var(--color-accent)]"
                />
                {t("correct")}
              </label>
              <input
                value={o.text}
                onChange={(e) => setOption(oi, { text: e.target.value })}
                aria-label={t("optionN", { n: oi + 1 })}
                aria-invalid={Boolean(errorAt(errors, `${prefix}.options.${oi}.text`))}
                className={controlClasses(Boolean(errorAt(errors, `${prefix}.options.${oi}.text`)))}
              />
              <RowControls
                index={oi}
                count={options.length}
                label={t("optionN", { n: oi + 1 })}
                onMove={(to) => onChange({ options: moveItem(options, oi, to) })}
                onRemove={
                  options.length > 2 ? () => onChange({ options: options.filter((_, i) => i !== oi) }) : undefined
                }
              />
            </li>
          ))}
        </ul>
        <ListError errors={errors} path={`${prefix}.options`} />
        {options.length < 6 ? (
          <AddButton
            label={t("addOption")}
            onClick={() => onChange({ options: [...options, { text: "", correct: false }] })}
          />
        ) : null}
      </div>
      <TextArea
        label={t("explanation")}
        value={question.explanation ?? ""}
        rows={2}
        hint={t("explanationHint")}
        error={errorAt(errors, `${prefix}.explanation`)}
        onChange={(v) => onChange({ explanation: v })}
      />
    </fieldset>
  );
}

/** JSON-edited visual types get the generic editor; the four form types have their own. */
export const FORM_VISUAL_EDITORS = {
  comparison: ComparisonEditor,
  timeline: TimelineEditor,
  calculator: CalculatorEditor,
  "knowledge-check": KnowledgeCheckEditor,
} as const;
