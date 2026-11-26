"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { controlClasses } from "@/components/ui/input";
import { checkScenario } from "../schemas";
import { messagesAt, mergeMessages, parseJson, toJsonText, withoutCheckpoints } from "../state";
import type { LabDefinition, PathMessages, Scenario } from "../types";
import { ENGINE_FORMS } from "./engine-forms";
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
  // The checkpoints tab owns scenario.checkpoints.
  const own = (m: PathMessages) => Object.fromEntries(Object.entries(m).filter(([p]) => !p.startsWith("scenario.checkpoints")));
  const live = checkScenario(def.engine, def.scenario);
  const messages = own(mergeMessages(messagesAt(errors, "scenario"), live));

  return (
    <div className="flex flex-col gap-4">
      <Alert tone="info">{t("secret")}</Alert>
      {Form ? (
        <Form scenario={def.scenario} errors={errors} onChange={onChange} />
      ) : (
        <JsonScenario def={def} onChange={onChange} onValidity={onValidity} />
      )}
      <PathList messages={messages} label={t("problems")} />
    </div>
  );
}

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
