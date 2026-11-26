"use client";

import { ArrowDown, ArrowUp, Plus, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import type { Scenario } from "../../types";
import type { EngineFormProps } from "../engine-forms";
import { Field } from "../parts";

type Rule = { action: "allow" | "deny"; proto: "tcp" | "udp" | "any"; port?: number | null; source: string };

const MAX_RULES = 20;

/** The firewall at the start of the lab: the server, the default policy and the rules, in order. */
export function FirewallForm({ scenario, errors, onChange }: EngineFormProps) {
  const t = useTranslations("labAdmin.forms.firewall");
  const server = (scenario.server ?? { name: "", ip: "" }) as { name: string; ip: string };
  const rules = (scenario.rules ?? []) as Rule[];
  const set = (next: Partial<Scenario>) => onChange({ ...scenario, ...next });
  const setRule = (i: number, next: Rule) => set({ rules: rules.map((r, j) => (j === i ? next : r)) });
  const move = (i: number, by: -1 | 1) => {
    const next = [...rules];
    [next[i], next[i + by]] = [next[i + by], next[i]];
    set({ rules: next });
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="panel grid gap-5 p-5 sm:grid-cols-3 sm:p-6">
        <Field label={t("serverName")} messages={errors["scenario.server.name"]}>
          {({ id, invalid, describedBy }) => (
            <input
              id={id}
              value={server.name}
              aria-invalid={invalid || undefined}
              aria-describedby={describedBy}
              onChange={(e) => set({ server: { ...server, name: e.target.value } })}
              className={controlClasses(invalid)}
            />
          )}
        </Field>
        <Field label={t("serverIp")} hint={t("serverIpHint")} messages={errors["scenario.server.ip"]}>
          {({ id, invalid, describedBy }) => (
            <input
              id={id}
              value={server.ip}
              spellCheck={false}
              aria-invalid={invalid || undefined}
              aria-describedby={describedBy}
              onChange={(e) => set({ server: { ...server, ip: e.target.value } })}
              className={controlClasses(invalid, "font-mono text-sm")}
            />
          )}
        </Field>
        <Field label={t("default")} hint={t("defaultHint")} messages={errors["scenario.default"]}>
          {({ id, invalid }) => (
            <select
              id={id}
              value={String(scenario.default ?? "allow")}
              onChange={(e) => set({ default: e.target.value })}
              className={controlClasses(invalid)}
            >
              <option value="allow">{t("allow")}</option>
              <option value="deny">{t("deny")}</option>
            </select>
          )}
        </Field>
      </div>

      <section aria-labelledby="firewall-rules-heading" className="panel flex flex-col gap-4 p-5 sm:p-6">
        <div className="flex flex-col gap-1">
          <h2 id="firewall-rules-heading" className="text-[length:var(--text-title)]">
            {t("rules")}
          </h2>
          <p className="max-w-[40rem] text-sm">{t("rulesIntro", { max: MAX_RULES })}</p>
        </div>
        {rules.length === 0 ? <p className="text-sm">{t("noRules")}</p> : null}
        <ol className="flex flex-col gap-3">
          {rules.map((rule, i) => (
            <li
              key={i}
              className="grid gap-3 rounded-card border border-rule bg-paper-2 p-4 sm:grid-cols-[2rem_repeat(4,minmax(0,1fr))_auto] sm:items-start"
            >
              <span className="pt-2 text-sm font-medium text-ink sm:pt-9">{i + 1}</span>
              <Field label={t("action")} messages={errors[`scenario.rules.${i}.action`]}>
                {({ id, invalid }) => (
                  <select
                    id={id}
                    value={rule.action}
                    onChange={(e) => setRule(i, { ...rule, action: e.target.value as Rule["action"] })}
                    className={controlClasses(invalid)}
                  >
                    <option value="allow">{t("allow")}</option>
                    <option value="deny">{t("deny")}</option>
                  </select>
                )}
              </Field>
              <Field label={t("proto")} messages={errors[`scenario.rules.${i}.proto`]}>
                {({ id, invalid }) => (
                  <select
                    id={id}
                    value={rule.proto}
                    onChange={(e) => setRule(i, { ...rule, proto: e.target.value as Rule["proto"] })}
                    className={controlClasses(invalid)}
                  >
                    <option value="tcp">tcp</option>
                    <option value="udp">udp</option>
                    <option value="any">{t("any")}</option>
                  </select>
                )}
              </Field>
              <Field label={t("port")} hint={t("portHint")} messages={errors[`scenario.rules.${i}.port`]}>
                {({ id, invalid, describedBy }) => (
                  <input
                    id={id}
                    inputMode="numeric"
                    value={rule.port ?? ""}
                    aria-invalid={invalid || undefined}
                    aria-describedby={describedBy}
                    onChange={(e) => {
                      const { port: _port, ...rest } = rule;
                      void _port;
                      const digits = e.target.value.replace(/\D/g, "");
                      setRule(i, digits === "" ? rest : { ...rest, port: Number(digits) });
                    }}
                    className={controlClasses(invalid, "font-mono text-sm")}
                  />
                )}
              </Field>
              <Field label={t("source")} hint={t("sourceHint")} messages={errors[`scenario.rules.${i}.source`]}>
                {({ id, invalid, describedBy }) => (
                  <input
                    id={id}
                    value={rule.source}
                    spellCheck={false}
                    aria-invalid={invalid || undefined}
                    aria-describedby={describedBy}
                    onChange={(e) => setRule(i, { ...rule, source: e.target.value })}
                    className={controlClasses(invalid, "font-mono text-sm")}
                  />
                )}
              </Field>
              <div className="flex gap-1 sm:pt-7">
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={i === 0}
                  onClick={() => move(i, -1)}
                  aria-label={t("moveUp", { number: i + 1 })}
                >
                  <ArrowUp aria-hidden />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  disabled={i === rules.length - 1}
                  onClick={() => move(i, 1)}
                  aria-label={t("moveDown", { number: i + 1 })}
                >
                  <ArrowDown aria-hidden />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => set({ rules: rules.filter((_, j) => j !== i) })}
                  aria-label={t("removeRule", { number: i + 1 })}
                >
                  <Trash2 aria-hidden />
                </Button>
              </div>
            </li>
          ))}
        </ol>
        <Button
          variant="secondary"
          className="self-start"
          disabled={rules.length >= MAX_RULES}
          onClick={() => set({ rules: [...rules, { action: "allow", proto: "tcp", port: 80, source: "any" }] })}
        >
          <Plus aria-hidden />
          {t("addRule")}
        </Button>
        {errors["scenario.rules"] ? (
          <p role="alert" className="text-sm text-bad">
            {errors["scenario.rules"].join(" ")}
          </p>
        ) : null}
      </section>
    </div>
  );
}
