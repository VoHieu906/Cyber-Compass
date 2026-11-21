"use client";

import { ArrowDown, ArrowUp, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { SegmentedControl } from "@/components/ui/segmented-control";
import { cn } from "@/lib/utils";
import type { FirewallRule, FirewallScenario, FirewallState, LabAction, LabActionResult, Packet } from "../types";

/**
 * The firewall's rule list, default policy and packet tester. Every change and test runs on the
 * server (D28); the verdicts shown are the engine's, never computed here.
 */
export function FirewallConsole({
  scenario,
  state,
  busy,
  onAction,
}: {
  scenario: FirewallScenario;
  state: FirewallState;
  busy: boolean;
  onAction: (action: LabAction) => Promise<LabActionResult | null>;
}) {
  const t = useTranslations("labs.firewall");
  const rules = state.rules;

  return (
    <section aria-label={t("label", { server: scenario.server.name })} className="flex flex-col gap-6">
      <p className="text-sm">
        {t("server", { name: scenario.server.name, ip: scenario.server.ip })} {t("order")}
      </p>

      <div className="flex flex-col gap-2">
        <h3 id="rules-heading" className="text-base font-medium text-ink">
          {t("rules")}
        </h3>
        <div className="relative overflow-x-auto rounded-card border border-rule">
          <table aria-labelledby="rules-heading" className="w-full min-w-[34rem] text-sm">
            <thead className="bg-paper-3 text-left text-muted">
              <tr>
                <th scope="col" className="px-3 py-2 font-medium">#</th>
                <th scope="col" className="px-3 py-2 font-medium">{t("action")}</th>
                <th scope="col" className="px-3 py-2 font-medium">{t("proto")}</th>
                <th scope="col" className="px-3 py-2 font-medium">{t("port")}</th>
                <th scope="col" className="px-3 py-2 font-medium">{t("source")}</th>
                <th scope="col" className="px-3 py-2 font-medium">
                  <span className="sr-only">{t("controls")}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {rules.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-3 py-3 text-center">
                    {t("noRules")}
                  </td>
                </tr>
              ) : (
                rules.map((rule, i) => {
                  const name = t("ruleName", { number: i + 1 });
                  return (
                    <tr key={`${i}-${rule.action}-${rule.proto}-${rule.port}-${rule.source}`} className="border-t border-rule">
                      <td className="measured px-3 py-2 text-muted">{i + 1}</td>
                      <td className="px-3 py-2">
                        <Verdict verdict={rule.action} />
                      </td>
                      <td className="measured px-3 py-2 text-ink">{rule.proto}</td>
                      <td className="measured px-3 py-2 text-ink">{rule.port ?? t("anyPort")}</td>
                      <td className="measured px-3 py-2 text-ink">{rule.source}</td>
                      <td className="px-2 py-1">
                        <span className="flex justify-end gap-1">
                          <IconButton label={t("moveUp", { rule: name })} disabled={busy || i === 0} onClick={() => onAction({ type: "move_rule", from: i, to: i - 1 })}>
                            <ArrowUp aria-hidden className="size-4" />
                          </IconButton>
                          <IconButton label={t("moveDown", { rule: name })} disabled={busy || i === rules.length - 1} onClick={() => onAction({ type: "move_rule", from: i, to: i + 1 })}>
                            <ArrowDown aria-hidden className="size-4" />
                          </IconButton>
                          <IconButton label={t("remove", { rule: name })} disabled={busy} onClick={() => onAction({ type: "remove_rule", index: i })}>
                            <Trash2 aria-hidden className="size-4" />
                          </IconButton>
                        </span>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
        <div className="flex flex-wrap items-center gap-3 text-sm">
          <span className="text-ink">{t("default")}</span>
          <SegmentedControl
            label={t("default")}
            options={[
              { value: "allow", label: t("allow") },
              { value: "deny", label: t("deny") },
            ]}
            value={state.default}
            disabled={busy}
            onChange={(policy) => void onAction({ type: "set_default", policy })}
          />
        </div>
      </div>

      <AddRule busy={busy} full={rules.length >= scenario.max_rules} onAdd={(rule, top) => onAction({ type: "add_rule", rule, position: top ? 0 : rules.length })} />
      <PacketTester busy={busy} state={state} onTest={(packet) => onAction({ type: "test", packet })} />
    </section>
  );
}

function Verdict({ verdict }: { verdict: "allow" | "deny" }) {
  const t = useTranslations("labs.firewall");
  return (
    <span className={cn("inline-flex rounded-chip border px-1.5 text-sm font-medium", verdict === "allow" ? "border-accent bg-accent-soft text-ink" : "border-bad bg-bad-soft text-bad")}>
      {t(verdict)}
    </span>
  );
}

function IconButton({ label, disabled, onClick, children }: { label: string; disabled: boolean; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      aria-label={label}
      disabled={disabled}
      onClick={onClick}
      className="flex size-8 items-center justify-center rounded-control text-ink transition-[background-color] duration-[var(--dur-micro)] hover:bg-paper-3 active:bg-paper-2 disabled:cursor-not-allowed disabled:opacity-35"
    >
      {children}
    </button>
  );
}

function AddRule({ busy, full, onAdd }: { busy: boolean; full: boolean; onAdd: (rule: FirewallRule, top: boolean) => void }) {
  const t = useTranslations("labs.firewall");
  const [rule, setRule] = useState<{ action: FirewallRule["action"]; proto: FirewallRule["proto"]; port: string; source: string; top: boolean }>({
    action: "allow",
    proto: "tcp",
    port: "",
    source: "any",
    top: false,
  });

  function submit(e: FormEvent) {
    e.preventDefault();
    // Empty means any port. Anything that is not a whole number is sent as typed, so the server
    // refuses it with a message instead of it silently becoming "any".
    const port = rule.port.trim();
    onAdd({ action: rule.action, proto: rule.proto, port: port === "" ? null : /^\d+$/.test(port) ? Number(port) : (port as unknown as number), source: rule.source.trim() || "any" }, rule.top);
  }

  return (
    <form onSubmit={submit} aria-labelledby="add-rule-heading" className="flex flex-col gap-3 rounded-card border border-rule bg-paper-2 p-4">
      <h3 id="add-rule-heading" className="text-base font-medium text-ink">
        {t("addRule")}
      </h3>
      <div className="grid gap-3 sm:grid-cols-[repeat(4,minmax(0,1fr))]">
        <Select label={t("action")} value={rule.action} onChange={(v) => setRule({ ...rule, action: v as FirewallRule["action"] })} options={[["allow", t("allow")], ["deny", t("deny")]]} />
        <Select label={t("proto")} value={rule.proto} onChange={(v) => setRule({ ...rule, proto: v as FirewallRule["proto"] })} options={[["tcp", "tcp"], ["udp", "udp"], ["any", t("anyProto")]]} />
        <Text label={t("port")} placeholder={t("anyPort")} value={rule.port} inputMode="numeric" onChange={(v) => setRule({ ...rule, port: v })} />
        <Text label={t("source")} placeholder="10.0.10.0/24" value={rule.source} onChange={(v) => setRule({ ...rule, source: v })} />
      </div>
      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-sm text-ink">
          <input type="checkbox" checked={rule.top} onChange={(e) => setRule({ ...rule, top: e.target.checked })} className="size-4 accent-[var(--color-accent)]" />
          {t("addAtTop")}
        </label>
        <Button type="submit" size="sm" disabled={busy || full}>
          {t("add")}
        </Button>
        {full ? <span className="text-sm">{t("full")}</span> : null}
      </div>
    </form>
  );
}

function PacketTester({ busy, state, onTest }: { busy: boolean; state: FirewallState; onTest: (packet: Packet) => void }) {
  const t = useTranslations("labs.firewall");
  const [packet, setPacket] = useState({ proto: "tcp" as Packet["proto"], port: "443", source: "203.0.113.9" });
  const latest = state.tests.at(-1);

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault();
        onTest({ proto: packet.proto, port: Number(packet.port), source: packet.source.trim() });
      }}
      aria-labelledby="test-heading"
      className="flex flex-col gap-3 rounded-card border border-rule bg-paper-2 p-4"
    >
      <h3 id="test-heading" className="text-base font-medium text-ink">
        {t("testPacket")}
      </h3>
      <div className="grid gap-3 sm:grid-cols-[repeat(3,minmax(0,1fr))]">
        <Select label={t("proto")} value={packet.proto} onChange={(v) => setPacket({ ...packet, proto: v as Packet["proto"] })} options={[["tcp", "tcp"], ["udp", "udp"]]} />
        <Text label={t("port")} value={packet.port} inputMode="numeric" onChange={(v) => setPacket({ ...packet, port: v })} />
        <Text label={t("from")} value={packet.source} onChange={(v) => setPacket({ ...packet, source: v })} />
      </div>
      <Button type="submit" size="sm" variant="secondary" className="self-start" disabled={busy}>
        {t("test")}
      </Button>
      <div aria-live="polite">
        {latest ? (
          <p className="flex flex-wrap items-center gap-2 text-sm text-ink">
            <span className="measured">
              {latest.proto}/{latest.port} {t("fromIp", { ip: latest.source })}
            </span>
            <Verdict verdict={latest.verdict} />
            <span>{latest.rule === null ? t("byDefault") : t("byRule", { number: latest.rule + 1 })}</span>
          </p>
        ) : null}
      </div>
    </form>
  );
}

function Select({ label, value, onChange, options }: { label: string; value: string; onChange: (v: string) => void; options: [string, string][] }) {
  return (
    <label className="flex flex-col gap-1.5 text-sm text-ink">
      {label}
      <select value={value} onChange={(e) => onChange(e.target.value)} className={controlClasses(false, "appearance-auto pr-2")}>
        {options.map(([v, l]) => (
          <option key={v} value={v}>
            {l}
          </option>
        ))}
      </select>
    </label>
  );
}

function Text({
  label,
  value,
  onChange,
  placeholder,
  inputMode,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  placeholder?: string;
  inputMode?: "numeric";
}) {
  return (
    <label className="flex flex-col gap-1.5 text-sm text-ink">
      {label}
      <input value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} inputMode={inputMode} maxLength={64} className={controlClasses(false, "measured")} />
    </label>
  );
}
