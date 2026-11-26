"use client";

import { FilePlus, Trash2 } from "lucide-react";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import type { Scenario } from "../../types";
import type { EngineFormProps } from "../engine-forms";
import { Field } from "../parts";

type Files = Record<string, string>;

/** The shell lab's machine: who and where the learner is, and the virtual files they can read. */
export function ShellForm({ scenario, errors, onChange }: EngineFormProps) {
  const t = useTranslations("labAdmin.forms.shell");
  const files = (scenario.files ?? {}) as Files;
  const paths = Object.keys(files);
  const [picked, setPicked] = useState<string | null>(null);
  // The file being edited; falls back to the first when the picked one was renamed or removed.
  const current = picked !== null && picked in files ? picked : (paths[0] ?? null);
  const set = (next: Partial<Scenario>) => onChange({ ...scenario, ...next });
  const text = (key: "hostname" | "user" | "home" | "cwd") => (
    <Field label={t(key)} hint={t(`${key}Hint`)} messages={errors[`scenario.${key}`]}>
      {({ id, invalid, describedBy }) => (
        <input
          id={id}
          value={String(scenario[key] ?? "")}
          spellCheck={false}
          aria-invalid={invalid || undefined}
          aria-describedby={describedBy}
          onChange={(e) => set({ [key]: e.target.value })}
          className={controlClasses(invalid)}
        />
      )}
    </Field>
  );

  /** Changes one file's path or text, keeping the files in the order they are listed. */
  function updateFile(path: string, next: { path?: string; text?: string }) {
    const entries = Object.entries(files).map(([p, content]): [string, string] =>
      p === path ? [next.path ?? p, next.text ?? content] : [p, content],
    );
    set({ files: Object.fromEntries(entries) });
    if (next.path !== undefined) setPicked(next.path);
  }

  function addFile() {
    let n = 1;
    while (`/home/${scenario.user || "learner"}/file-${n}.txt` in files) n++;
    const path = `/home/${scenario.user || "learner"}/file-${n}.txt`;
    set({ files: { ...files, [path]: "" } });
    setPicked(path);
  }

  const dirs = (scenario.dirs as string[] | undefined) ?? [];
  const fileErrors = Object.entries(errors).filter(([p]) => p === "scenario.files" || p.startsWith("scenario.files."));

  return (
    <div className="flex flex-col gap-6">
      <div className="panel grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
        {text("hostname")}
        {text("user")}
        {text("home")}
        {text("cwd")}
      </div>

      <section aria-labelledby="shell-files-heading" className="panel flex flex-col gap-4 p-5 sm:p-6">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div className="flex flex-col gap-1">
            <h2 id="shell-files-heading" className="text-[length:var(--text-title)]">
              {t("files")}
            </h2>
            <p className="max-w-[40rem] text-sm">{t("filesIntro")}</p>
          </div>
          <Button variant="secondary" onClick={addFile}>
            <FilePlus aria-hidden />
            {t("addFile")}
          </Button>
        </div>
        <div className="grid gap-4 md:grid-cols-[minmax(0,16rem)_minmax(0,1fr)]">
          <ul aria-label={t("files")} className="flex max-h-72 flex-col gap-1 overflow-y-auto md:max-h-[28rem]">
            {paths.map((p) => (
              <li key={p}>
                <button
                  type="button"
                  onClick={() => setPicked(p)}
                  aria-current={p === current ? "true" : undefined}
                  className={`w-full rounded-control border px-3 py-2 text-left font-mono text-xs break-all transition-[background-color,border-color] duration-[var(--dur-micro)] ${
                    p === current
                      ? "border-accent bg-accent-soft text-ink"
                      : "border-rule bg-paper-2 text-ink hover:bg-paper-3"
                  }`}
                >
                  {p}
                </button>
              </li>
            ))}
          </ul>
          {current !== null ? (
            <div className="flex min-w-0 flex-col gap-4">
              <Field label={t("path")} hint={t("pathHint")}>
                {({ id, describedBy }) => (
                  <input
                    id={id}
                    value={current}
                    spellCheck={false}
                    aria-describedby={describedBy}
                    onChange={(e) => updateFile(current, { path: e.target.value })}
                    className={controlClasses(false, "font-mono text-sm")}
                  />
                )}
              </Field>
              <Field label={t("content")} hint={t("contentHint")}>
                {({ id, describedBy }) => (
                  <textarea
                    id={id}
                    value={files[current]}
                    rows={14}
                    spellCheck={false}
                    autoComplete="off"
                    aria-describedby={describedBy}
                    onChange={(e) => updateFile(current, { text: e.target.value })}
                    className={controlClasses(false, "h-auto py-2 font-mono text-sm")}
                  />
                )}
              </Field>
              <Button
                variant="ghost"
                size="sm"
                className="self-start"
                disabled={paths.length === 1}
                onClick={() => {
                  const { [current]: _removed, ...rest } = files;
                  void _removed;
                  set({ files: rest });
                  setPicked(null);
                }}
              >
                <Trash2 aria-hidden />
                {t("removeFile")}
              </Button>
            </div>
          ) : null}
        </div>
        {fileErrors.length ? (
          <ul role="alert" className="flex flex-col gap-1 text-sm text-bad">
            {fileErrors.flatMap(([p, list]) => list.map((m) => <li key={`${p}-${m}`}>{m}</li>))}
          </ul>
        ) : null}
      </section>

      <Field label={t("dirs")} hint={t("dirsHint")} messages={errors["scenario.dirs"]}>
        {({ id, describedBy }) => (
          <textarea
            id={id}
            value={dirs.join("\n")}
            rows={3}
            spellCheck={false}
            aria-describedby={describedBy}
            onChange={(e) => {
              const lines = e.target.value.split("\n");
              const { dirs: _dirs, ...rest } = scenario;
              void _dirs;
              onChange(lines.some((l) => l.trim() !== "") ? { ...rest, dirs: lines.map((l) => l.trim()) } : rest);
            }}
            className={controlClasses(false, "h-auto py-2 font-mono text-sm")}
          />
        )}
      </Field>
    </div>
  );
}
