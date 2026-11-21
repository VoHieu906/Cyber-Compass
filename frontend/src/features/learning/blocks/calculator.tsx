"use client";

import { useTranslations } from "next-intl";
import { useId, useState } from "react";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import {
  cidr,
  entropy,
  modeToOctal,
  modeToSymbolic,
  parseMode,
  roughDuration,
  WHO,
  type Mode,
  type Perm,
} from "./calculators";
import type { Calculator as CalculatorData } from "./schemas";

/** Runs entirely in the browser: nothing typed here is stored or sent. */
export function Calculator({ data }: { data: CalculatorData }) {
  return (
    <div className="flex flex-col gap-4 rounded-control border border-rule bg-paper-2 p-4 @xl:p-5">
      {data.tool === "cidr" ? <CidrTool initial={data.initial ?? "192.168.1.130/26"} /> : null}
      {data.tool === "chmod" ? <ChmodTool initial={data.initial ?? "754"} /> : null}
      {data.tool === "password-entropy" ? <EntropyTool initial={data.initial ?? ""} /> : null}
    </div>
  );
}

function Field({
  label,
  value,
  onChange,
  invalid,
  hint,
  type = "text",
  extra,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  invalid: boolean;
  hint: string;
  type?: string;
  extra?: Record<string, string>;
}) {
  const id = useId();
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-ink">
        {label}
      </label>
      <input
        id={id}
        type={type}
        value={value}
        spellCheck={false}
        autoComplete="off"
        autoCapitalize="off"
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={invalid}
        aria-describedby={`${id}-hint`}
        className={controlClasses(invalid, "measured @xl:max-w-[20rem]")}
        {...extra}
      />
      <p id={`${id}-hint`} className={cn("text-sm", invalid ? "text-bad" : "text-muted")}>
        {hint}
      </p>
    </div>
  );
}

function Results({ rows }: { rows: [string, React.ReactNode][] }) {
  return (
    <dl className="grid gap-x-6 gap-y-2 text-sm @xl:grid-cols-2" aria-live="polite">
      {rows.map(([label, value]) => (
        <div key={label} className="flex flex-col gap-0.5">
          <dt>{label}</dt>
          <dd className="measured text-ink">{value}</dd>
        </div>
      ))}
    </dl>
  );
}

function CidrTool({ initial }: { initial: string }) {
  const t = useTranslations("learn.calculator");
  const [value, setValue] = useState(initial);
  const r = cidr(value);
  return (
    <>
      <Field
        label={t("cidrInput")}
        value={value}
        onChange={setValue}
        invalid={!r}
        hint={r ? t("cidrHint") : t("cidrInvalid")}
      />
      {r ? (
        <>
          <Results
            rows={[
              [t("mask"), `${r.mask} (/${r.prefix})`],
              [t("network"), r.network],
              [t("broadcast"), r.broadcast],
              [t("hostRange"), r.usableHosts > 0 ? `${r.firstHost} – ${r.lastHost}` : "–"],
              [t("usableHosts"), r.usableHosts.toLocaleString()],
              [t("wildcard"), r.wildcard],
            ]}
          />
          <div className="flex flex-col gap-1 overflow-x-auto text-sm">
            <p className="text-muted">{t("inBinary")}</p>
            <code className="measured whitespace-pre text-ink">
              <BinaryRow bits={r.addressBits} prefix={r.prefix} />
              {"\n"}
              <BinaryRow bits={r.maskBits} prefix={r.prefix} />
            </code>
            <p className="text-muted">{t("binaryKey", { prefix: r.prefix })}</p>
          </div>
        </>
      ) : null}
    </>
  );
}

/** Network bits in the accent colour, host bits muted. */
function BinaryRow({ bits, prefix }: { bits: string; prefix: number }) {
  let seen = 0;
  return (
    <>
      {bits.split("").map((c, i) => {
        if (c === ".")
          return (
            <span key={i} className="text-muted">
              .
            </span>
          );
        const network = seen++ < prefix;
        return (
          <span key={i} className={network ? "text-accent" : "text-muted"}>
            {c}
          </span>
        );
      })}
    </>
  );
}

function ChmodTool({ initial }: { initial: string }) {
  const t = useTranslations("learn.calculator");
  const [text, setText] = useState(initial);
  const [mode, setMode] = useState<Mode>(() => parseMode(initial) ?? parseMode("644")!);
  const parsed = parseMode(text);

  function type(value: string) {
    setText(value);
    const m = parseMode(value);
    if (m) setMode(m);
  }
  function toggle(who: (typeof WHO)[number], perm: keyof Perm) {
    const next = { ...mode, [who]: { ...mode[who], [perm]: !mode[who][perm] } };
    setMode(next);
    setText(modeToOctal(next));
  }

  return (
    <>
      <Field
        label={t("chmodInput")}
        value={text}
        onChange={type}
        invalid={!parsed}
        hint={parsed ? t("chmodHint") : t("chmodInvalid")}
      />
      <div className="overflow-x-auto">
        <table className="text-sm">
          <caption className="sr-only">{t("permissions")}</caption>
          <thead>
            <tr>
              <td />
              {(["read", "write", "execute"] as const).map((p) => (
                <th key={p} scope="col" className="px-3 pb-1 font-medium text-muted">
                  {t(p)}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {WHO.map((who) => (
              <tr key={who}>
                <th scope="row" className="pr-3 text-left font-medium text-ink">
                  {t(who)}
                </th>
                {(["read", "write", "execute"] as const).map((p) => (
                  <td key={p} className="px-3 py-1 text-center">
                    <input
                      type="checkbox"
                      checked={mode[who][p]}
                      onChange={() => toggle(who, p)}
                      aria-label={`${t(who)}: ${t(p)}`}
                      className="size-4 accent-[var(--color-accent)]"
                    />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      <Results
        rows={[
          [t("octal"), modeToOctal(mode)],
          [t("symbolic"), modeToSymbolic(mode)],
          [t("command"), `chmod ${modeToOctal(mode)} file`],
          [
            t("special"),
            [mode.setuid && "setuid", mode.setgid && "setgid", mode.sticky && "sticky"].filter(Boolean).join(", ") ||
              t("none"),
          ],
        ]}
      />
      {mode.others.write ? (
        <p className="rounded-control border border-attn bg-attn-soft p-3 text-sm text-ink">{t("worldWritable")}</p>
      ) : null}
    </>
  );
}

const RATING_CLASS = {
  "very-weak": "bg-bad",
  weak: "bg-bad",
  fair: "bg-attn",
  strong: "bg-ok",
  "very-strong": "bg-ok",
} as const;

function EntropyTool({ initial }: { initial: string }) {
  const t = useTranslations("learn.calculator");
  const [value, setValue] = useState(initial);
  const [show, setShow] = useState(false);
  const r = entropy(value);
  const time = roughDuration(r.crackSeconds);

  return (
    <>
      <p className="rounded-control border border-attn bg-attn-soft p-3 text-sm text-ink">{t("entropyWarning")}</p>
      <Field
        label={t("entropyInput")}
        type={show ? "text" : "password"}
        value={value}
        onChange={setValue}
        invalid={false}
        hint={t("entropyHint")}
        extra={{ autoComplete: "new-password", "data-1p-ignore": "true", "data-lpignore": "true" }}
      />
      <label className="flex items-center gap-2 text-sm text-ink">
        <input
          type="checkbox"
          checked={show}
          onChange={(e) => setShow(e.target.checked)}
          className="accent-[var(--color-accent)]"
        />
        {t("showPassword")}
      </label>
      {value ? (
        <>
          <div className="flex flex-col gap-1.5">
            <div className="h-2 overflow-hidden rounded-full bg-paper-3" aria-hidden>
              <div
                className={cn("h-full rounded-full", RATING_CLASS[r.rating])}
                style={{ width: `${Math.min(100, (r.bits / 100) * 100)}%` }}
              />
            </div>
            <p className="text-sm font-medium text-ink" aria-live="polite">
              {t(`rating.${r.rating}`)}
            </p>
          </div>
          <Results
            rows={[
              [t("length"), String(r.length)],
              [t("pool"), String(r.pool)],
              [t("bits"), `≈ ${r.bits}`],
              [
                t("crackTime"),
                time.unit === "instant" || time.unit === "eons"
                  ? t(`time.${time.unit}`)
                  : t(`time.${time.unit}`, { count: time.count }),
              ],
            ]}
          />
          {r.warnings.length ? (
            <ul className="flex flex-col gap-1 text-sm text-bad">
              {r.warnings.map((w) => (
                <li key={w}>{t(`warnings.${w}`)}</li>
              ))}
            </ul>
          ) : null}
          <p className="text-sm text-muted">{t("entropyCaveat")}</p>
        </>
      ) : null}
    </>
  );
}
