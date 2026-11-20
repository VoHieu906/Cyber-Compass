"use client";

import { Play } from "lucide-react";
import { useTranslations } from "next-intl";
import { useEffect, useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { SceneFrame, type SceneStatus } from "@/features/learning/blocks/scene-frame";
import { cn } from "@/lib/utils";
import { ASPECTS, describeIssues, LIMITS, sceneSchema, scriptSchema, SHAPES } from "@/sandbox/scene3d/schema";
import { errorAt, errorsUnder, type FieldErrors } from "../editor-state";
import { InputRow, JsonField, SelectRow, TextArea } from "./fields";

type Props = {
  at: string;
  payload: Record<string, unknown>;
  errors: FieldErrors;
  replacePayload: (payload: Record<string, unknown>) => void;
};

const PREVIEW_DELAY_MS = { scene: 500, script: 1000 };

/** Title, text description, caption and aspect: the parts every 3D block shares. */
function CommonFields({ at, payload, errors, replacePayload }: Props) {
  const t = useTranslations("content.scene");
  const set = (patch: Record<string, unknown>) => replacePayload({ ...payload, ...patch });
  const err = (field: string) => errorAt(errors, `${at}.payload.${field}`);
  return (
    <div className="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
      <InputRow
        label={t("title")}
        value={String(payload.title ?? "")}
        error={err("title")}
        hint={t("titleHint")}
        onChange={(v) => set({ title: v })}
      />
      <SelectRow
        label={t("aspect")}
        value={String(payload.aspect ?? "16:9")}
        options={ASPECTS.map((a) => ({ value: a, label: a }))}
        error={err("aspect")}
        onChange={(v) => set({ aspect: v })}
      />
      <div className="md:col-span-2">
        <TextArea
          label={t("description")}
          value={String(payload.description ?? "")}
          rows={3}
          error={err("description")}
          hint={t("descriptionHint")}
          onChange={(v) => set({ description: v })}
        />
      </div>
      <div className="md:col-span-2">
        <InputRow
          label={t("caption")}
          optional
          value={String(payload.caption ?? "")}
          error={err("caption")}
          onChange={(v) => set({ caption: v === "" ? null : v })}
        />
      </div>
    </div>
  );
}

/**
 * The live preview: the same sandboxed SceneFrame learners get, fed with the unsaved payload
 * once it passes the shared schema. The last valid version stays on screen while you type.
 */
function LivePreview({
  kind,
  payload,
  valid,
  delay,
  runKey,
}: {
  kind: "scene-3d" | "script-3d";
  payload: Record<string, unknown>;
  valid: boolean;
  delay: number;
  runKey?: number;
}) {
  const t = useTranslations("content.scene");
  const [shown, setShown] = useState<Record<string, unknown> | null>(valid ? payload : null);
  const [status, setStatus] = useState<SceneStatus | null>(null);
  const serialized = JSON.stringify(payload);

  useEffect(() => {
    if (!valid) return;
    const timer = setTimeout(() => setShown(JSON.parse(serialized) as Record<string, unknown>), delay);
    return () => clearTimeout(timer);
  }, [serialized, valid, delay, runKey]);

  return (
    <div className="flex flex-col gap-2">
      <p className="text-sm font-medium text-ink">{t("livePreview")}</p>
      {shown ? (
        <SceneFrame key={runKey} kind={kind} payload={shown} debug onStatus={setStatus} />
      ) : (
        <p className="rounded-control border border-dashed border-rule-2 p-6 text-center text-sm">
          {t("previewWaiting")}
        </p>
      )}
      {status?.status === "error" || status?.status === "stalled" ? (
        <Alert tone="error">
          {status.status === "stalled" ? t("stalled") : t("runtimeError")}
          {status.message ? (
            <code className="measured mt-1 block text-xs">
              {status.line ? t("atLine", { line: status.line }) + " " : null}
              {status.message}
            </code>
          ) : null}
        </Alert>
      ) : null}
    </div>
  );
}

function ClientIssues({ issues }: { issues: { path: string; message: string }[] }) {
  const t = useTranslations("content.scene");
  if (issues.length === 0) return null;
  return (
    <div className="rounded-control border border-[color-mix(in_oklab,var(--color-bad)_45%,var(--color-rule))] bg-bad-soft px-3 py-2 text-sm">
      <p className="font-medium text-ink">{t("notYetValid")}</p>
      <ul className="mt-1 flex flex-col gap-0.5">
        {issues.map((issue, i) => (
          <li key={i}>
            {issue.path ? <code className="measured text-xs">{issue.path}</code> : null} {issue.message}
          </li>
        ))}
      </ul>
    </div>
  );
}

export function SceneEditor({
  at,
  payload,
  errors,
  replacePayload,
  onJsonValidity,
}: Props & { onJsonValidity: (invalid: boolean) => void }) {
  const t = useTranslations("content.scene");
  const { title, description, caption, aspect, ...sceneData } = payload;
  const parsed = sceneSchema.safeParse(payload);
  const sceneErrors = Object.fromEntries(
    Object.entries(errorsUnder(errors, `${at}.payload`)).filter(
      ([key]) => !["title", "description", "caption", "aspect"].includes(key.split(".")[0]),
    ),
  );

  return (
    <div className="flex flex-col gap-5">
      <CommonFields at={at} payload={payload} errors={errors} replacePayload={replacePayload} />
      <div className="grid gap-5 xl:grid-cols-2">
        <div className="flex min-w-0 flex-col gap-3">
          <JsonField
            label={t("sceneJson")}
            value={sceneData}
            errors={sceneErrors}
            onChange={(data) => replacePayload({ title, description, caption, aspect, ...data })}
            onValidity={onJsonValidity}
          />
          <ClientIssues
            issues={
              parsed.success
                ? []
                : describeIssues(parsed.error).filter(
                    (i) => !["title", "description", "caption", "aspect"].includes(i.path.split(".")[0]),
                  )
            }
          />
          <SceneReference />
        </div>
        <LivePreview kind="scene-3d" payload={payload} valid={parsed.success} delay={PREVIEW_DELAY_MS.scene} />
      </div>
    </div>
  );
}

export function ScriptEditor({ at, payload, errors, replacePayload }: Props) {
  const t = useTranslations("content.scene");
  const codeId = useId();
  const [runKey, setRunKey] = useState(0);
  const code = String(payload.code ?? "");
  const codeError = errorAt(errors, `${at}.payload.code`);
  const parsed = scriptSchema.safeParse(payload);

  return (
    <div className="flex flex-col gap-5">
      <CommonFields at={at} payload={payload} errors={errors} replacePayload={replacePayload} />
      <div className="grid gap-5 xl:grid-cols-2">
        <div className="flex min-w-0 flex-col gap-2">
          <div className="flex items-end justify-between gap-3">
            <label htmlFor={codeId} className="text-sm font-medium text-ink">
              {t("code")}
            </label>
            <span className="measured text-xs text-muted">
              {code.length.toLocaleString()} / {LIMITS.scriptBytes.toLocaleString()}
            </span>
          </div>
          <textarea
            id={codeId}
            value={code}
            spellCheck={false}
            rows={18}
            maxLength={LIMITS.scriptBytes}
            onKeyDown={(e) => {
              // Tab indents inside the editor; Escape then Tab leaves it (keyboard users are never trapped).
              if (e.key === "Tab" && !e.shiftKey && !e.currentTarget.dataset.escaped) {
                e.preventDefault();
                const el = e.currentTarget;
                const { selectionStart: start, selectionEnd: end } = el;
                const next = code.slice(0, start) + "  " + code.slice(end);
                replacePayload({ ...payload, code: next });
                requestAnimationFrame(() => el.setSelectionRange(start + 2, start + 2));
              }
              if (e.key === "Escape") e.currentTarget.dataset.escaped = "1";
            }}
            onBlur={(e) => delete e.currentTarget.dataset.escaped}
            onChange={(e) => replacePayload({ ...payload, code: e.target.value })}
            aria-invalid={Boolean(codeError)}
            aria-describedby={`${codeId}-hint`}
            className={controlClasses(
              Boolean(codeError),
              "measured h-auto py-2 text-sm leading-relaxed whitespace-pre",
            )}
          />
          <p id={`${codeId}-hint`} className={cn("text-sm", codeError ? "text-bad" : "text-muted")}>
            {codeError ?? t("codeHint")}
          </p>
          <div>
            <Button variant="secondary" size="sm" onClick={() => setRunKey((k) => k + 1)} disabled={!parsed.success}>
              <Play aria-hidden />
              {t("run")}
            </Button>
          </div>
          <ScriptReference />
        </div>
        <LivePreview
          kind="script-3d"
          payload={payload}
          valid={parsed.success}
          delay={PREVIEW_DELAY_MS.script}
          runKey={runKey}
        />
      </div>
    </div>
  );
}

function SceneReference() {
  const t = useTranslations("content.scene.reference");
  return (
    <details className="rounded-control border border-rule px-3 py-2 text-sm">
      <summary className="cursor-pointer rounded-chip font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">{t("title")}</summary>
      <dl className="[&_code]:measured mt-2 flex flex-col gap-2 [&_code]:text-xs [&_code]:text-ink [&_dt]:font-medium [&_dt]:text-ink">
        <div>
          <dt>camera</dt>
          <dd>{t("camera")}</dd>
        </div>
        <div>
          <dt>objects</dt>
          <dd>
            {t("objects", { max: LIMITS.objects })} <code>{SHAPES.join(", ")}</code>
          </dd>
        </div>
        <div>
          <dt>animation</dt>
          <dd>{t("animation")}</dd>
        </div>
        <div>
          <dt>links</dt>
          <dd>{t("links", { max: LIMITS.links })}</dd>
        </div>
        <div>
          <dt>steps</dt>
          <dd>{t("steps", { max: LIMITS.steps })}</dd>
        </div>
        <div>
          <dt>lights</dt>
          <dd>{t("lights", { max: LIMITS.lights })}</dd>
        </div>
      </dl>
    </details>
  );
}

function ScriptReference() {
  const t = useTranslations("content.scene.scriptReference");
  return (
    <details className="rounded-control border border-rule px-3 py-2 text-sm">
      <summary className="cursor-pointer rounded-chip font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">{t("title")}</summary>
      <div className="mt-2 flex flex-col gap-2">
        <p>{t("intro")}</p>
        <ul className="[&_code]:measured flex flex-col gap-1 [&_code]:text-xs [&_code]:text-ink">
          <li>
            <code>THREE, scene, camera, controls, renderer</code>: {t("objects")}
          </li>
          <li>
            <code>onFrame((delta, elapsed) =&gt; …)</code>: {t("onFrame")}
          </li>
          <li>
            <code>label(text, {"{ color, scale }"})</code>: {t("label")}
          </li>
          <li>
            <code>theme, reducedMotion</code>: {t("flags")}
          </li>
        </ul>
        <p className="text-muted">{t("limits")}</p>
      </div>
    </details>
  );
}
