"use client";

import { ArrowDown, ArrowUp } from "lucide-react";
import { useTranslations } from "next-intl";
import { useRef, useState, type ReactNode } from "react";
import { controlClasses } from "@/components/ui/input";
import { renderInline } from "@/features/learning/components/prose";
import { cn } from "@/lib/utils";
import type { Choice, ExerciseQuestion, Response } from "../types";

type Props = {
  question: ExerciseQuestion;
  response: Response | undefined;
  onChange: (response: Response) => void;
  /** The id of the question's heading, which names every group of inputs. */
  labelledBy: string;
};

/**
 * The answer input for each of the seven question types. Every type works with the keyboard
 * alone: radio and checkbox groups, text fields, selects for matching, and move buttons for
 * ordering (no drag and drop, so no extra dependency and nothing pointer-only).
 */
export function QuestionInput({ question, response, onChange, labelledBy }: Props) {
  switch (question.type) {
    case "single_choice":
    case "scenario":
      return (
        <ChoiceList
          name={`q-${question.id}`}
          kind="radio"
          options={question.payload.options}
          selected={response && "option" in response ? [response.option] : []}
          onToggle={(id) => onChange({ option: id })}
          labelledBy={labelledBy}
        />
      );
    case "multi_choice": {
      const selected = response && "options" in response ? response.options : [];
      return (
        <ChoiceList
          name={`q-${question.id}`}
          kind="checkbox"
          options={question.payload.options}
          selected={selected}
          onToggle={(id) => onChange({ options: selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id] })}
          labelledBy={labelledBy}
        />
      );
    }
    case "true_false":
      return <TrueFalse name={`q-${question.id}`} value={response && "value" in response ? response.value : null} onChange={(value) => onChange({ value })} labelledBy={labelledBy} />;
    case "fill_blank":
      return (
        <Blanks
          text={question.payload.text}
          count={question.payload.blanks}
          values={response && "blanks" in response ? response.blanks : []}
          onChange={(blanks) => onChange({ blanks })}
        />
      );
    case "matching":
      return (
        <Matching
          left={question.payload.left}
          right={question.payload.right}
          matches={response && "matches" in response ? response.matches : question.payload.left.map(() => null)}
          onChange={(matches) => onChange({ matches })}
          labelledBy={labelledBy}
        />
      );
    case "ordering":
      return (
        <Ordering
          items={question.payload.items}
          order={response && "order" in response ? response.order : question.payload.items.map((i) => i.id)}
          onChange={(order) => onChange({ order })}
          labelledBy={labelledBy}
        />
      );
  }
}

function OptionCard({ on, children }: { on: boolean; children: ReactNode }) {
  return (
    <label
      className={cn(
        "flex cursor-pointer items-start gap-3 rounded-control border px-4 py-3 transition-[border-color,background-color] duration-[var(--dur-micro)]",
        on ? "border-accent bg-accent-soft" : "border-rule bg-paper-2 hover:border-rule-2",
      )}
    >
      {children}
    </label>
  );
}

function ChoiceList({
  name,
  kind,
  options,
  selected,
  onToggle,
  labelledBy,
}: {
  name: string;
  kind: "radio" | "checkbox";
  options: Choice[];
  selected: number[];
  onToggle: (id: number) => void;
  labelledBy: string;
}) {
  const t = useTranslations("exercise.input");
  return (
    <fieldset aria-labelledby={labelledBy} className="flex flex-col gap-2">
      {kind === "checkbox" ? <legend className="mb-1 text-sm">{t("chooseAll")}</legend> : null}
      {options.map((o) => {
        const on = selected.includes(o.id);
        return (
          <OptionCard key={o.id} on={on}>
            <input type={kind} name={name} checked={on} onChange={() => onToggle(o.id)} className="mt-1 accent-[var(--color-accent)]" />
            <span className="flex-1 text-ink">{renderInline(o.label)}</span>
          </OptionCard>
        );
      })}
    </fieldset>
  );
}

function TrueFalse({ name, value, onChange, labelledBy }: { name: string; value: boolean | null; onChange: (value: boolean) => void; labelledBy: string }) {
  const t = useTranslations("exercise.input");
  return (
    <fieldset aria-labelledby={labelledBy} className="grid gap-2 sm:grid-cols-2">
      {[true, false].map((v) => (
        <OptionCard key={String(v)} on={value === v}>
          <input type="radio" name={name} checked={value === v} onChange={() => onChange(v)} className="mt-1 accent-[var(--color-accent)]" />
          <span className="flex-1 text-ink">{v ? t("true") : t("false")}</span>
        </OptionCard>
      ))}
    </fieldset>
  );
}

/** The sentence with a text field in place of each ___. */
function Blanks({ text, count, values, onChange }: { text: string; count: number; values: string[]; onChange: (values: string[]) => void }) {
  const t = useTranslations("exercise.input");
  const parts = text.split("___");
  const set = (i: number, v: string) => onChange(Array.from({ length: count }, (_, j) => (j === i ? v : (values[j] ?? ""))));
  return (
    <p className="rounded-control border border-rule bg-paper-2 px-4 py-4 text-[1.0625rem] leading-[2.6] text-ink">
      {parts.map((part, i) => (
        <span key={i}>
          {renderInline(part)}
          {i < count ? (
            <input
              type="text"
              value={values[i] ?? ""}
              onChange={(e) => set(i, e.target.value)}
              maxLength={100}
              autoComplete="off"
              spellCheck={false}
              aria-label={t("blank", { number: i + 1, total: count })}
              className={controlClasses(false, "mx-1 inline-block h-9 w-[11ch] align-middle text-base")}
            />
          ) : null}
        </span>
      ))}
    </p>
  );
}

function Matching({
  left,
  right,
  matches,
  onChange,
  labelledBy,
}: {
  left: Choice[];
  right: Choice[];
  matches: (number | null)[];
  onChange: (matches: (number | null)[]) => void;
  labelledBy: string;
}) {
  const t = useTranslations("exercise.input");
  return (
    <fieldset aria-labelledby={labelledBy} className="flex flex-col gap-2">
      {left.map((l, i) => {
        const id = `${labelledBy}-match-${l.id}`;
        return (
          <div key={l.id} className="grid items-center gap-2 rounded-control border border-rule bg-paper-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] sm:gap-4">
            <label htmlFor={id} className="font-medium text-ink">
              {l.label}
            </label>
            <select
              id={id}
              value={matches[i] ?? ""}
              onChange={(e) => onChange(matches.map((m, j) => (j === i ? (e.target.value === "" ? null : Number(e.target.value)) : m)))}
              className={controlClasses(false, "appearance-auto pr-2")}
            >
              <option value="">{t("choose")}</option>
              {right.map((r) => (
                <option key={r.id} value={r.id}>
                  {r.label}
                </option>
              ))}
            </select>
          </div>
        );
      })}
    </fieldset>
  );
}

/** Items with move buttons. Focus stays on the moved item's button, and the move is announced. */
function Ordering({ items, order, onChange, labelledBy }: { items: Choice[]; order: number[]; onChange: (order: number[]) => void; labelledBy: string }) {
  const t = useTranslations("exercise.input");
  const buttons = useRef(new Map<string, HTMLButtonElement | null>());
  const [announcement, setAnnouncement] = useState("");
  const label = (id: number) => items.find((i) => i.id === id)?.label ?? "";

  function move(from: number, to: number, direction: "up" | "down") {
    const next = [...order];
    const [id] = next.splice(from, 1);
    next.splice(to, 0, id);
    onChange(next);
    setAnnouncement(t("moved", { item: label(id), position: to + 1, total: order.length }));
    // At the first or last place the button in that direction disappears; keep focus on the other one.
    const target = (to === 0 && direction === "up") || (to === order.length - 1 && direction === "down") ? (direction === "up" ? "down" : "up") : direction;
    requestAnimationFrame(() => buttons.current.get(`${id}-${target}`)?.focus());
  }

  return (
    <div className="flex flex-col gap-2">
      <ol aria-labelledby={labelledBy} className="flex flex-col gap-2">
        {order.map((id, i) => (
          <li key={id} className="flex items-center gap-3 rounded-control border border-rule bg-paper-2 py-2 pr-2 pl-4">
            <span className="measured w-5 shrink-0 text-sm text-muted">{i + 1}</span>
            <span className="min-w-0 flex-1 text-ink">{renderInline(label(id))}</span>
            <span className="flex shrink-0 gap-1">
              <MoveButton
                ref={(el) => void buttons.current.set(`${id}-up`, el)}
                disabled={i === 0}
                label={t("moveUp", { item: label(id) })}
                onClick={() => move(i, i - 1, "up")}
              >
                <ArrowUp aria-hidden className="size-4" />
              </MoveButton>
              <MoveButton
                ref={(el) => void buttons.current.set(`${id}-down`, el)}
                disabled={i === order.length - 1}
                label={t("moveDown", { item: label(id) })}
                onClick={() => move(i, i + 1, "down")}
              >
                <ArrowDown aria-hidden className="size-4" />
              </MoveButton>
            </span>
          </li>
        ))}
      </ol>
      <p aria-live="polite" className="sr-only">
        {announcement}
      </p>
    </div>
  );
}

function MoveButton({
  ref,
  disabled,
  label,
  onClick,
  children,
}: {
  ref: (el: HTMLButtonElement | null) => void;
  disabled: boolean;
  label: string;
  onClick: () => void;
  children: ReactNode;
}) {
  return (
    <button
      ref={ref}
      type="button"
      disabled={disabled}
      aria-label={label}
      onClick={onClick}
      className="flex size-9 items-center justify-center rounded-control border border-rule bg-paper-3 text-ink transition-[border-color] duration-[var(--dur-micro)] hover:border-rule-2 disabled:cursor-not-allowed disabled:opacity-35"
    >
      {children}
    </button>
  );
}
