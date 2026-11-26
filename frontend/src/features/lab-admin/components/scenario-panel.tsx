"use client";

import { useTranslations } from "next-intl";
import { useCallback, useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { controlClasses } from "@/components/ui/input";
import { checkScenario } from "../schemas";
import { messagesAt, mergeMessages, parseJson, toJsonText, withoutCheckpoints } from "../state";
import type { LabDefinition, PathMessages, Scenario } from "../types";
import { ENGINE_FORMS } from "./engine-forms";
import { ViewSwitch, type View } from "./forms/view-switch";
import { PathList, useValidity, type ValidityReporter } from "./parts";

/**
 * The engine's scenario apart from its checkpoints (those have their own tab). An engine with a
 * registered form shows it; every other engine edits the scenario as JSON, with the schema's problems
 * listed by path as you type and the server's after a save.
 */
export function ScenarioPanel({
  def,
  errors,
  onChange,
  onValidity,
}: {
  def: LabDefinition;
  errors: PathMessages;
  onChange: (scenario: Scenario) => void;
  onValidity: ValidityReporter;
}) {
  const t = useTranslations("labAdmin.scenario");
  const Form = ENGINE_FORMS[def.engine];
  const [view, setView] = useState<View>("form");
  // Whether the JSON box holds text that does not parse: the form cannot take over from it until it does.
  const [jsonBad, setJsonBad] = useState(false);
  const reportValidity = useCallback<ValidityReporter>(
    (key, invalid) => {
      setJsonBad(invalid);
      onValidity(key, invalid);
    },
    [onValidity],
  );
  // The checkpoints tab owns scenario.checkpoints.
  const own = (m: PathMessages) =>
    Object.fromEntries(Object.entries(m).filter(([p]) => !p.startsWith("scenario.checkpoints")));
  const live = checkScenario(def.engine, def.scenario);
  const all = mergeMessages(messagesAt(errors, "scenario"), live);
  const messages = own(all);
  const showForm = Form !== undefined && view === "form";

  return (
    <div className="flex flex-col gap-4">
      <Alert tone="info">{t("secret")}</Alert>
      {Form ? (
        <ViewSwitch view={view} onChange={setView} blocked={view === "json" && jsonBad} label={t("view")} />
      ) : null}
      {showForm ? (
        <Form scenario={def.scenario} errors={all} onChange={onChange} />
      ) : (
        <JsonScenario def={def} onChange={onChange} onValidity={reportValidity} />
      )}
      {/* A form shows each problem beside its field; this list is for the ones no field owns, and for the JSON view. */}
      <PathList messages={showForm ? onlyUnowned(messages) : messages} label={t("problems")} />
    </div>
  );
}

/** Problems about the scenario as a whole, which no field of a form shows. */
const onlyUnowned = (m: PathMessages): PathMessages =>
  Object.fromEntries(Object.entries(m).filter(([p]) => p === "scenario"));

function JsonScenario({
  def,
  onChange,
  onValidity,
}: {
  def: LabDefinition;
  onChange: (scenario: Scenario) => void;
  onValidity: ValidityReporter;
}) {
  const t = useTranslations("labAdmin.scenario");
  const id = useId();
  const reportValidity = useValidity(onValidity);
  const outside = toJsonText(withoutCheckpoints(def.scenario));
  const [text, setText] = useState(outside);
  const [seen, setSeen] = useState(outside);
  const [parseError, setParseError] = useState<string | null>(null);

  // A restore or a discard replaces the scenario from outside; typing a valid change does not.
  if (outside !== seen) {
    setSeen(outside);
    if (!parseError) setText(outside);
  }

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {t("json")}
      </label>
      <textarea
        id={id}
        value={text}
        spellCheck={false}
        autoComplete="off"
        rows={Math.min(28, Math.max(10, text.split("\n").length + 1))}
        aria-invalid={parseError ? true : undefined}
        aria-describedby={parseError ? `${id}-error` : `${id}-hint`}
        onChange={(e) => {
          setText(e.target.value);
          const parsed = parseJson(e.target.value);
          if (parsed.ok && parsed.value && typeof parsed.value === "object" && !Array.isArray(parsed.value)) {
            setParseError(null);
            reportValidity(false);
            setSeen(toJsonText(parsed.value));
            onChange({ ...(parsed.value as Record<string, unknown>), checkpoints: def.scenario.checkpoints });
          } else {
            setParseError(parsed.ok ? t("notObject") : parsed.error);
            reportValidity(true);
          }
        }}
        className={controlClasses(Boolean(parseError), "h-auto py-2 font-mono text-sm")}
      />
      {parseError ? (
        <p id={`${id}-error`} role="alert" className="text-sm text-bad">
          {t("parseError", { message: parseError })}
        </p>
      ) : (
        <p id={`${id}-hint`} className="text-xs text-muted">
          {t("jsonHint")}
        </p>
      )}
    </div>
  );
}
