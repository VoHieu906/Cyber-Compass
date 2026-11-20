"use client";

import { useTranslations } from "next-intl";
import { useId } from "react";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { errorAt, errorsUnder, type FieldErrors } from "../editor-state";
import type { BlockTypeInfo, EditorSection } from "../types";
import { RowControls, StringListEditor } from "./list-editors";
import { InputRow, JsonField, SelectRow, TextArea } from "./fields";
import { MediaPicker } from "./media-picker";
import { isVisualBlock } from "@/features/learning/blocks/visual-block";
import { SceneEditor, ScriptEditor } from "./scene-editors";
import { FORM_VISUAL_EDITORS, JsonVisualEditor } from "./visual-editors";

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
 * diagrams, layer stacks, sequences, flows, trees, topologies, bit fields, step-throughs) get a
 * JSON editor checked against the shared contract as you type. Visual blocks show the learner
 * renderer as a live preview. Server messages appear next to the field they name.
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
    case "scene-3d":
      return (
        <SceneEditor
          at={at}
          payload={p}
          errors={errors}
          replacePayload={replacePayload}
          onJsonValidity={onJsonValidity}
        />
      );
    case "script-3d":
      return <ScriptEditor at={at} payload={p} errors={errors} replacePayload={replacePayload} />;
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
    case "comparison":
    case "timeline":
    case "calculator":
    case "knowledge-check": {
      const Editor = FORM_VISUAL_EDITORS[section.type];
      return <Editor type={section.type} at={at} payload={p} errors={errors} replacePayload={replacePayload} />;
    }
    default:
      if (isVisualBlock(section.type)) {
        return (
          <JsonVisualEditor
            type={section.type}
            at={at}
            payload={p}
            errors={errors}
            replacePayload={replacePayload}
            blockType={blockType}
            onJsonValidity={onJsonValidity}
          />
        );
      }
      // Future types: the whole payload as JSON, validated by the server's schema.
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
