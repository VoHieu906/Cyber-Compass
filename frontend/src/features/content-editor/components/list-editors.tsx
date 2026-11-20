"use client";

import { ArrowDown, ArrowUp, Plus, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId } from "react";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import { errorAt, moveItem, type FieldErrors } from "../editor-state";
import type { Resource } from "../types";

const iconButton =
  "inline-flex size-8 shrink-0 items-center justify-center rounded-control text-muted transition-[color,background-color] duration-[var(--dur-micro)] hover:bg-paper-3 hover:text-ink disabled:pointer-events-none disabled:opacity-35 [&_svg]:size-4";

/** Move up, move down and (when onRemove is given) remove, each labelled with the item it acts on. */
export function RowControls({
  index,
  count,
  onMove,
  onRemove,
  label,
}: {
  index: number;
  count: number;
  onMove: (to: number) => void;
  onRemove?: () => void;
  label: string;
}) {
  const t = useTranslations("content.editor");
  return (
    <div className="flex shrink-0 items-center">
      <button
        type="button"
        className={iconButton}
        disabled={index === 0}
        onClick={() => onMove(index - 1)}
        aria-label={t("moveUp", { item: label })}
      >
        <ArrowUp aria-hidden />
      </button>
      <button
        type="button"
        className={iconButton}
        disabled={index === count - 1}
        onClick={() => onMove(index + 1)}
        aria-label={t("moveDown", { item: label })}
      >
        <ArrowDown aria-hidden />
      </button>
      {onRemove ? (
        <button
          type="button"
          className={cn(iconButton, "hover:text-bad")}
          onClick={onRemove}
          aria-label={t("remove", { item: label })}
        >
          <X aria-hidden />
        </button>
      ) : null}
    </div>
  );
}

/**
 * An ordered list of short texts (objectives, takeaways, steps). `errorPrefix` is the
 * server key of the list (e.g. "objectives" or "sections.2.payload.items"), so each row
 * shows its own message.
 */
export function StringListEditor({
  label,
  items,
  onChange,
  errors,
  errorPrefix,
  maxItems,
  multiline = false,
  addLabel,
}: {
  label: string;
  items: string[];
  onChange: (items: string[]) => void;
  errors: FieldErrors;
  errorPrefix: string;
  maxItems: number;
  multiline?: boolean;
  addLabel: string;
}) {
  const t = useTranslations("content.editor");
  const groupId = useId();
  const listError = errorAt(errors, errorPrefix);

  return (
    <fieldset className="flex min-w-0 flex-col gap-2" aria-describedby={listError ? `${groupId}-error` : undefined}>
      <legend className="mb-1.5 text-sm font-medium text-ink">{label}</legend>
      {items.length === 0 ? <p className="text-sm">{t("emptyList")}</p> : null}
      <ol className="flex flex-col gap-2">
        {items.map((item, i) => {
          const error = errorAt(errors, `${errorPrefix}.${i}`);
          const rowLabel = `${label} ${i + 1}`;
          const common = {
            value: item,
            "aria-label": rowLabel,
            "aria-invalid": Boolean(error),
            onChange: (e: { target: { value: string } }) =>
              onChange(items.map((x, j) => (j === i ? e.target.value : x))),
          };
          return (
            <li key={i} className="flex flex-col gap-1">
              <div className="flex items-start gap-2">
                <span aria-hidden className="measured mt-2.5 w-5 shrink-0 text-right text-xs text-muted">
                  {i + 1}
                </span>
                {multiline ? (
                  <textarea {...common} rows={2} className={controlClasses(Boolean(error), "h-auto py-2")} />
                ) : (
                  <input {...common} className={controlClasses(Boolean(error))} />
                )}
                <RowControls
                  index={i}
                  count={items.length}
                  label={rowLabel}
                  onMove={(to) => onChange(moveItem(items, i, to))}
                  onRemove={() => onChange(items.filter((_, j) => j !== i))}
                />
              </div>
              {error ? <p className="pl-7 text-sm text-bad">{error}</p> : null}
            </li>
          );
        })}
      </ol>
      {listError ? (
        <p id={`${groupId}-error`} className="text-sm text-bad">
          {listError}
        </p>
      ) : null}
      {items.length < maxItems ? (
        <div>
          <Button variant="ghost" size="sm" onClick={() => onChange([...items, ""])}>
            <Plus aria-hidden />
            {addLabel}
          </Button>
        </div>
      ) : null}
    </fieldset>
  );
}

export function ResourceListEditor({
  items,
  onChange,
  errors,
}: {
  items: Resource[];
  onChange: (items: Resource[]) => void;
  errors: FieldErrors;
}) {
  const t = useTranslations("content.editor");
  const update = (i: number, patch: Partial<Resource>) =>
    onChange(items.map((r, j) => (j === i ? { ...r, ...patch } : r)));

  return (
    <fieldset className="flex min-w-0 flex-col gap-2">
      <legend className="mb-1.5 text-sm font-medium text-ink">{t("resources")}</legend>
      {items.length === 0 ? <p className="text-sm">{t("emptyList")}</p> : null}
      <ol className="flex flex-col gap-3">
        {items.map((r, i) => {
          const labelError = errorAt(errors, `resources.${i}.label`);
          const urlError = errorAt(errors, `resources.${i}.url`);
          return (
            <li key={i} className="flex flex-col gap-1">
              <div className="flex items-start gap-2">
                <div className="grid min-w-0 flex-1 gap-2 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                  <input
                    value={r.label}
                    onChange={(e) => update(i, { label: e.target.value })}
                    placeholder={t("resourceLabel")}
                    aria-label={`${t("resourceLabel")} ${i + 1}`}
                    aria-invalid={Boolean(labelError)}
                    className={controlClasses(Boolean(labelError))}
                  />
                  <input
                    value={r.url}
                    type="url"
                    inputMode="url"
                    onChange={(e) => update(i, { url: e.target.value })}
                    placeholder="https://"
                    aria-label={`${t("resourceUrl")} ${i + 1}`}
                    aria-invalid={Boolean(urlError)}
                    className={controlClasses(Boolean(urlError), "measured text-sm")}
                  />
                </div>
                <RowControls
                  index={i}
                  count={items.length}
                  label={`${t("resources")} ${i + 1}`}
                  onMove={(to) => onChange(moveItem(items, i, to))}
                  onRemove={() => onChange(items.filter((_, j) => j !== i))}
                />
              </div>
              {labelError || urlError ? <p className="text-sm text-bad">{labelError ?? urlError}</p> : null}
            </li>
          );
        })}
      </ol>
      {items.length < 10 ? (
        <div>
          <Button variant="ghost" size="sm" onClick={() => onChange([...items, { label: "", url: "https://" }])}>
            <Plus aria-hidden />
            {t("addResource")}
          </Button>
        </div>
      ) : null}
    </fieldset>
  );
}
