"use client";

import { Send } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { cn } from "@/lib/utils";
import type { HttpMessage, HttpRequest, HttpScenario, HttpState, LabAction, LabActionResult } from "../types";

/**
 * Recorded traffic and a request composer for the simulated app (D28). Requests go to the
 * server's simulation of the app, never to the internet; the response shown is what the
 * simulation answered.
 */
export function HttpConsole({
  scenario,
  state,
  busy,
  onAction,
}: {
  scenario: HttpScenario;
  state: HttpState;
  busy: boolean;
  onAction: (action: LabAction) => Promise<LabActionResult | null>;
}) {
  const t = useTranslations("labs.http");
  const [response, setResponse] = useState<{ request: HttpRequest; response: HttpMessage } | null>(null);

  return (
    <section aria-label={t("label", { app: scenario.app.name })} className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h3 className="text-base font-medium text-ink">{t("captured")}</h3>
        <ul className="flex flex-col gap-2">
          {scenario.captured.map((exchange, i) => (
            <li key={i}>
              <details className="group rounded-card border border-rule bg-paper-2">
                <summary className="measured flex cursor-pointer items-center justify-between gap-3 px-4 py-2.5 text-sm text-ink">
                  <span className="truncate">
                    {exchange.request.method} {exchange.request.path}
                  </span>
                  <Status status={exchange.response.status} />
                </summary>
                <div className="grid gap-3 border-t border-rule p-4 md:grid-cols-2">
                  <Message title={t("request")} first={`${exchange.request.method} ${exchange.request.path} HTTP/1.1`} headers={{ Host: new URL(scenario.app.origin).host, ...exchange.request.headers }} body={exchange.request.body ?? ""} />
                  <Message title={t("response")} first={`HTTP/1.1 ${exchange.response.status}`} headers={exchange.response.headers} body={exchange.response.body} />
                </div>
              </details>
            </li>
          ))}
        </ul>
      </div>

      <Composer
        scenario={scenario}
        busy={busy}
        onSend={async (request) => {
          const result = await onAction({ type: "send", request });
          if (result?.output.response) setResponse({ request, response: result.output.response });
        }}
      />

      <div aria-live="polite">
        {response ? (
          <div className="flex flex-col gap-2">
            <h3 className="text-base font-medium text-ink">{t("lastResponse")}</h3>
            <Message title={`${response.request.method} ${response.request.path}`} first={`HTTP/1.1 ${response.response.status}`} headers={response.response.headers} body={response.response.body} />
          </div>
        ) : null}
      </div>

      {state.sent.length ? (
        <div className="flex flex-col gap-2">
          <h3 className="text-base font-medium text-ink">{t("sent")}</h3>
          <ol className="flex flex-col text-sm">
            {[...state.sent].reverse().map((s, i) => (
              <li key={i} className="measured flex items-center justify-between gap-3 border-b border-rule py-1.5 text-ink">
                <span className="truncate">
                  {s.request.method} {s.request.path}
                </span>
                <Status status={s.status} />
              </li>
            ))}
          </ol>
        </div>
      ) : null}
    </section>
  );
}

function Composer({ scenario, busy, onSend }: { scenario: HttpScenario; busy: boolean; onSend: (request: HttpRequest) => void }) {
  const t = useTranslations("labs.http");
  const [method, setMethod] = useState("GET");
  const [path, setPath] = useState("/api/orders/1001");
  const [useSession, setUseSession] = useState(true);
  const [headers, setHeaders] = useState("Accept: application/json");
  const [body, setBody] = useState("");
  const [error, setError] = useState<string | null>(null);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const parsed: Record<string, string> = {};
    for (const line of headers.split("\n").map((l) => l.trim()).filter(Boolean)) {
      const at = line.indexOf(":");
      if (at < 1) {
        setError(t("headerFormat"));
        return;
      }
      parsed[line.slice(0, at).trim()] = line.slice(at + 1).trim();
    }
    setError(null);
    onSend({
      method,
      path: path.trim(),
      headers: parsed,
      cookies: useSession ? { [scenario.session.name]: scenario.session.value } : {},
      body: method === "POST" || method === "PUT" ? body : "",
    });
  }

  return (
    <form onSubmit={submit} aria-labelledby="compose-heading" className="flex flex-col gap-3 rounded-card border border-rule bg-paper-2 p-4">
      <h3 id="compose-heading" className="text-base font-medium text-ink">
        {t("compose")}
      </h3>
      <div className="grid gap-3 sm:grid-cols-[8rem_minmax(0,1fr)]">
        <label className="flex flex-col gap-1.5 text-sm text-ink">
          {t("method")}
          <select value={method} onChange={(e) => setMethod(e.target.value)} className={controlClasses(false, "appearance-auto pr-2")}>
            {scenario.methods.map((m) => (
              <option key={m}>{m}</option>
            ))}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-sm text-ink">
          {t("path", { origin: scenario.app.origin })}
          <input value={path} onChange={(e) => setPath(e.target.value)} maxLength={200} spellCheck={false} className={controlClasses(false, "measured")} />
        </label>
      </div>
      <label className="flex items-center gap-2 text-sm text-ink">
        <input type="checkbox" checked={useSession} onChange={(e) => setUseSession(e.target.checked)} className="size-4 accent-[var(--color-accent)]" />
        <span>
          {t("sendSession")} <span className="measured">{`${scenario.session.name}=${scenario.session.value}`}</span>
        </span>
      </label>
      <label className="flex flex-col gap-1.5 text-sm text-ink">
        {t("headers")}
        <textarea value={headers} onChange={(e) => setHeaders(e.target.value)} rows={2} spellCheck={false} className={controlClasses(Boolean(error), "measured h-auto py-2")} />
        {error ? <span className="text-bad">{error}</span> : null}
      </label>
      {method === "POST" || method === "PUT" ? (
        <label className="flex flex-col gap-1.5 text-sm text-ink">
          {t("body")}
          <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={3} maxLength={2000} spellCheck={false} className={controlClasses(false, "measured h-auto py-2")} />
        </label>
      ) : null}
      <Button type="submit" size="sm" className="self-start" loading={busy}>
        <Send aria-hidden />
        {t("send")}
      </Button>
    </form>
  );
}

function Status({ status }: { status: number }) {
  return <span className={cn("measured shrink-0 rounded-chip border px-1.5 text-xs", status < 300 ? "border-accent text-ink" : status < 400 ? "border-rule-2 text-ink" : "border-bad text-bad")}>{status}</span>;
}

function Message({ title, first, headers, body }: { title: string; first: string; headers: Record<string, string>; body: string }) {
  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <p className="text-sm text-muted">{title}</p>
      <pre className="measured overflow-x-auto rounded-control border border-rule bg-paper-3 p-3 text-xs leading-relaxed whitespace-pre-wrap break-all text-ink">
        {[first, ...Object.entries(headers).map(([k, v]) => `${k}: ${v}`)].join("\n")}
        {body ? `\n\n${body}` : ""}
      </pre>
    </div>
  );
}
