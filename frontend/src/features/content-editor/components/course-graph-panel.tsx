"use client";

import { Plus, RotateCcw, Save, X } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { useId, useMemo, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { CourseMap } from "@/features/course-map/components/course-map";
import type { CourseMapData } from "@/features/course-map/types";
import { ApiError } from "@/lib/api/client";
import { EDGE_TYPES, courseGraphSchema, type CourseGraph, type EdgeType } from "@/lib/graph/shapes/schemas";
import { restoreCourseGraph, saveCourseGraph } from "../api";
import { errorAt, type FieldErrors } from "../editor-state";
import { useCourseGraph, useCourseGraphMutation } from "../hooks";
import type { AdminCourseGraph } from "../types";
import { Labelled } from "./document-editor";

const EMPTY: CourseGraph = { version: 1, problem: "", layout: "auto", edges: [] };

/**
 * The course map of one course (F3, D58): saved at once, like the course structure, with the
 * lock the panel loaded (409 when someone saved since), an audit entry per save and a history to
 * restore from. A saved map is owned by the database: content imports leave it alone. Issues
 * (unplaced, unconnected, dangling lessons) are listed, never a reason to refuse a save.
 */
export function CourseGraphPanel({ courseId }: { courseId: number }) {
  const t = useTranslations("content.courses.map");
  const panel = useCourseGraph(courseId);
  if (panel.isError) return <Alert tone="error">{t("loadError")}</Alert>;
  if (!panel.data) return <div aria-hidden className="h-40 rounded-card bg-paper-3" />;
  return <GraphForm key={panel.data.course.id} data={panel.data} />;
}

function GraphForm({ data }: { data: AdminCourseGraph }) {
  const t = useTranslations("content.courses.map");
  const tm = useTranslations("courseMap");
  const format = useFormatter();
  const errorMessage = useErrorMessage();
  const courseId = data.course.id;
  const [graph, setGraph] = useState<CourseGraph | null>(data.graph);
  const save = useCourseGraphMutation(courseId, (g: CourseGraph) => saveCourseGraph(courseId, g, data.lock_version));
  const restore = useCourseGraphMutation(courseId, ({ id, side }: { id: number; side: "before" | "after" }) =>
    restoreCourseGraph(courseId, id, side, data.lock_version),
  );
  const ids = { problem: useId(), layout: useId() };
  const failed = [save, restore].find((m) => m.isError)?.error;
  const errors: FieldErrors = failed instanceof ApiError && failed.isValidation ? failed.fieldErrors : {};
  const err = (path: string) => errorAt(errors, `graph.${path}`);
  const titles = new Map(data.lessons.map((l) => [l.slug, l.title]));
  const lessonTitle = (slug: string) => titles.get(slug) ?? slug;

  const parsed = useMemo(() => (graph ? courseGraphSchema.safeParse(graph) : null), [graph]);
  // The preview shows what learners would see: published lessons only (the server does the same on the course page).
  const preview = useMemo<CourseMapData | null>(() => {
    if (!parsed?.success) return null;
    const live = data.lessons.filter((l) => l.status === "published");
    const slugs = new Set(live.map((l) => l.slug));
    const g = parsed.data;
    return {
      problem: g.problem,
      layout: g.layout,
      lanes: g.lanes ?? [],
      edges: g.edges.filter((e) => slugs.has(e.from) && slugs.has(e.to)),
      lessons: live.map((l) => ({
        slug: l.slug,
        title: l.title,
        placement: g.layout === "manual" ? (g.placement?.[l.slug] ?? null) : null,
        problem: l.architecture?.problem ?? null,
        shape: l.architecture?.shape ?? null,
        rows: l.architecture?.rows ?? null,
        cols: l.architecture?.cols ?? null,
        nodes: l.architecture?.nodes ?? [],
        edges: l.architecture?.edges ?? [],
      })),
    };
  }, [parsed, data.lessons]);

  if (!graph) {
    return (
      <div className="flex flex-col items-start gap-3">
        <p className="max-w-[60ch] text-sm">{t("none")}</p>
        <Button type="button" variant="secondary" size="sm" onClick={() => setGraph({ ...EMPTY })}>
          <Plus aria-hidden />
          {t("start")}
        </Button>
      </div>
    );
  }

  const set = (patch: Partial<CourseGraph>) => setGraph({ ...graph, ...patch });
  const placement = graph.placement ?? {};
  const setPlace = (slug: string, patch: Partial<{ col: number; row: number; lane: string }>) => {
    const next = { ...(placement[slug] ?? { col: 0, row: 0 }), ...patch };
    if (!next.lane) delete next.lane;
    set({ placement: { ...placement, [slug]: next } });
  };

  return (
    <form
      className="flex flex-col gap-5"
      onSubmit={(e) => {
        e.preventDefault();
        restore.reset();
        save.mutate(graph, { onSuccess: (fresh) => setGraph(fresh.graph) });
      }}
    >
      {failed ? (
        <Alert tone="error">
          {failed instanceof ApiError && failed.status === 409 ? t("conflict") : errorMessage(failed)}
        </Alert>
      ) : null}
      {save.isSuccess || restore.isSuccess ? (
        <Alert tone="success">{t(restore.isSuccess ? "restored" : "saved")}</Alert>
      ) : null}
      {data.owned_by_admin ? <p className="text-sm text-muted">{t("owned")}</p> : null}
      {data.issues.length ? (
        <Alert tone="info">
          <p>{t("issuesTitle")}</p>
          <ul className="list-disc pl-5">
            {data.issues.map((issue, i) => (
              <li key={i}>
                {t(`issues.${issue.type}`, {
                  lesson: lessonTitle(issue.lesson),
                  detail: issue.detail ? lessonTitle(issue.detail) : "",
                })}
              </li>
            ))}
          </ul>
        </Alert>
      ) : null}

      <Labelled id={ids.problem} label={t("problem")} hint={t("problemHint")} error={err("problem")}>
        <textarea
          id={ids.problem}
          rows={2}
          maxLength={200}
          value={graph.problem}
          onChange={(e) => set({ problem: e.target.value })}
          aria-invalid={Boolean(err("problem"))}
          className={controlClasses(Boolean(err("problem")), "h-auto py-2")}
        />
      </Labelled>
      <Labelled id={ids.layout} label={t("layout")} hint={t(`layoutHint.${graph.layout}`)} error={err("layout")}>
        <select
          id={ids.layout}
          value={graph.layout}
          onChange={(e) => set({ layout: e.target.value as CourseGraph["layout"] })}
          className={controlClasses(false, "sm:max-w-[16rem]")}
        >
          <option value="auto">{t("layoutAuto")}</option>
          <option value="manual">{t("layoutManual")}</option>
        </select>
      </Labelled>

      {graph.layout === "manual" ? (
        <>
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1.5 text-sm font-medium text-ink">{t("lanes")}</legend>
            <p className="text-sm text-muted">{t("lanesHint")}</p>
            {(graph.lanes ?? []).map((lane, i) => (
              <div key={i} className="flex items-end gap-2">
                <Field
                  label={t("laneLabel", { number: i + 1 })}
                  error={err(`lanes.${i}.label`) ?? err(`lanes.${i}.id`)}
                >
                  {(id) => (
                    <input
                      id={id}
                      value={lane.label}
                      maxLength={30}
                      onChange={(e) =>
                        set({ lanes: graph.lanes!.map((l, j) => (j === i ? { ...l, label: e.target.value } : l)) })
                      }
                      className={controlClasses(false)}
                    />
                  )}
                </Field>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  aria-label={t("removeLane", { number: i + 1 })}
                  onClick={() =>
                    set({
                      lanes: graph.lanes!.filter((_, j) => j !== i),
                      placement: Object.fromEntries(
                        Object.entries(placement).map(([s, p]) => [
                          s,
                          p.lane === lane.id ? { col: p.col, row: p.row } : p,
                        ]),
                      ),
                    })
                  }
                >
                  <X aria-hidden />
                </Button>
              </div>
            ))}
            <Button
              type="button"
              variant="secondary"
              size="sm"
              className="self-start"
              disabled={(graph.lanes?.length ?? 0) >= 6}
              onClick={() => {
                const taken = new Set((graph.lanes ?? []).map((l) => l.id));
                let n = (graph.lanes?.length ?? 0) + 1;
                while (taken.has(`lane-${n}`)) n++;
                set({ lanes: [...(graph.lanes ?? []), { id: `lane-${n}`, label: t("newLane") }] });
              }}
            >
              <Plus aria-hidden />
              {t("addLane")}
            </Button>
          </fieldset>
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-1.5 text-sm font-medium text-ink">{t("placement")}</legend>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted">
                  <th className="py-1 font-normal">{t("lesson")}</th>
                  <th className="py-1 font-normal">{t("column")}</th>
                  <th className="py-1 font-normal">{t("row")}</th>
                  {graph.lanes?.length ? <th className="py-1 font-normal">{t("lane")}</th> : null}
                </tr>
              </thead>
              <tbody>
                {data.lessons.map((l) => {
                  const place = placement[l.slug];
                  return (
                    <tr key={l.slug} className="border-t border-rule">
                      <th scope="row" className="py-1.5 pr-3 text-left font-normal text-ink">
                        {l.title}
                      </th>
                      {(["col", "row"] as const).map((axis) => (
                        <td key={axis} className="py-1.5 pr-2">
                          <select
                            aria-label={`${t(axis === "col" ? "column" : "row")}: ${l.title}`}
                            value={place?.[axis] ?? ""}
                            onChange={(e) =>
                              e.target.value === ""
                                ? set({
                                    placement: Object.fromEntries(
                                      Object.entries(placement).filter(([s]) => s !== l.slug),
                                    ),
                                  })
                                : setPlace(l.slug, { [axis]: Number(e.target.value) })
                            }
                            className={controlClasses(Boolean(err(`placement.${l.slug}.${axis}`)), "h-8 py-0")}
                          >
                            <option value="">{t("unplaced")}</option>
                            {Array.from({ length: axis === "col" ? 8 : 10 }, (_, n) => (
                              <option key={n} value={n}>
                                {n + 1}
                              </option>
                            ))}
                          </select>
                        </td>
                      ))}
                      {graph.lanes?.length ? (
                        <td className="py-1.5">
                          <select
                            aria-label={`${t("lane")}: ${l.title}`}
                            value={place?.lane ?? ""}
                            disabled={!place}
                            onChange={(e) => setPlace(l.slug, { lane: e.target.value })}
                            className={controlClasses(false, "h-8 py-0")}
                          >
                            <option value="">{t("noLane")}</option>
                            {graph.lanes.map((lane) => (
                              <option key={lane.id} value={lane.id}>
                                {lane.label}
                              </option>
                            ))}
                          </select>
                        </td>
                      ) : null}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </fieldset>
        </>
      ) : null}

      <fieldset className="flex min-w-0 flex-col gap-2">
        <legend className="mb-1.5 text-sm font-medium text-ink">{t("relations")}</legend>
        <p className="text-sm text-muted">{t("relationsHint")}</p>
        <ol className="flex flex-col gap-2">
          {graph.edges.map((edge, i) => (
            <li key={i} className="flex flex-wrap items-end gap-2">
              <Field label={t("from")} error={err(`edges.${i}.from`)}>
                {(id) => (
                  <LessonSelect
                    id={id}
                    value={edge.from}
                    lessons={data.lessons}
                    onChange={(from) => set({ edges: graph.edges.map((x, j) => (j === i ? { ...x, from } : x)) })}
                  />
                )}
              </Field>
              <Field label={t("type")} error={err(`edges.${i}.type`) ?? err(`edges.${i}`)}>
                {(id) => (
                  <select
                    id={id}
                    value={edge.type}
                    onChange={(e) =>
                      set({
                        edges: graph.edges.map((x, j) => (j === i ? { ...x, type: e.target.value as EdgeType } : x)),
                      })
                    }
                    className={controlClasses(false)}
                  >
                    {EDGE_TYPES.map((type) => (
                      <option key={type} value={type}>
                        {tm(`edges.${type}`)}
                      </option>
                    ))}
                  </select>
                )}
              </Field>
              <Field label={t("to")} error={err(`edges.${i}.to`)}>
                {(id) => (
                  <LessonSelect
                    id={id}
                    value={edge.to}
                    lessons={data.lessons}
                    onChange={(to) => set({ edges: graph.edges.map((x, j) => (j === i ? { ...x, to } : x)) })}
                  />
                )}
              </Field>
              <Field label={t("edgeLabel")} error={err(`edges.${i}.label`)}>
                {(id) => (
                  <input
                    id={id}
                    maxLength={24}
                    value={edge.label ?? ""}
                    onChange={(e) =>
                      set({
                        edges: graph.edges.map((x, j) => (j === i ? { ...x, label: e.target.value || undefined } : x)),
                      })
                    }
                    className={controlClasses(false)}
                  />
                )}
              </Field>
              <Button
                type="button"
                variant="ghost"
                size="sm"
                aria-label={t("removeRelation", { number: i + 1 })}
                onClick={() => set({ edges: graph.edges.filter((_, j) => j !== i) })}
              >
                <X aria-hidden />
              </Button>
            </li>
          ))}
        </ol>
        <Button
          type="button"
          variant="secondary"
          size="sm"
          className="self-start"
          disabled={data.lessons.length < 2 || graph.edges.length >= 80}
          onClick={() =>
            set({ edges: [...graph.edges, { from: data.lessons[0].slug, to: data.lessons[1].slug, type: "requires" }] })
          }
        >
          <Plus aria-hidden />
          {t("addRelation")}
        </Button>
      </fieldset>

      <div className="flex flex-wrap items-center gap-3">
        <Button type="submit" loading={save.isPending} disabled={!parsed?.success}>
          <Save aria-hidden />
          {t("save")}
        </Button>
        {parsed && !parsed.success ? <p className="text-sm text-bad">{t("fixFirst")}</p> : null}
      </div>

      <div className="flex flex-col gap-2">
        <h3 className="text-sm font-medium text-ink">{t("preview")}</h3>
        {preview ? <CourseMap map={preview} courseSlug={data.course.slug} courseTitle={data.course.title} /> : null}
      </div>

      {data.history.length ? (
        <div className="flex flex-col gap-2">
          <h3 className="text-sm font-medium text-ink">{t("history")}</h3>
          <ul className="flex flex-col divide-y divide-rule text-sm">
            {data.history.map((entry) => (
              <li key={entry.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                <span>
                  {t(entry.action === "course_graph.saved" ? "historySaved" : "historyRestored", {
                    who: entry.actor ?? t("someone"),
                  })}
                  {entry.at ? (
                    <span className="text-muted">
                      {" "}
                      · {format.dateTime(new Date(entry.at), { dateStyle: "medium", timeStyle: "short" })}
                    </span>
                  ) : null}
                </span>
                <span className="flex gap-2">
                  {entry.has_before ? (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      loading={restore.isPending}
                      onClick={() => (
                        save.reset(),
                        restore.mutate(
                          { id: entry.id, side: "before" },
                          { onSuccess: (fresh) => setGraph(fresh.graph) },
                        )
                      )}
                    >
                      <RotateCcw aria-hidden />
                      {t("restoreBefore")}
                    </Button>
                  ) : null}
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    loading={restore.isPending}
                    onClick={() => (
                      save.reset(),
                      restore.mutate({ id: entry.id, side: "after" }, { onSuccess: (fresh) => setGraph(fresh.graph) })
                    )}
                  >
                    <RotateCcw aria-hidden />
                    {t("restoreAfter")}
                  </Button>
                </span>
              </li>
            ))}
          </ul>
        </div>
      ) : null}
    </form>
  );
}

function LessonSelect({
  id,
  value,
  lessons,
  onChange,
}: {
  id: string;
  value: string;
  lessons: AdminCourseGraph["lessons"];
  onChange: (slug: string) => void;
}) {
  return (
    <select
      id={id}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      className={controlClasses(false, "min-w-[12rem]")}
    >
      {lessons.some((l) => l.slug === value) ? null : <option value={value}>{value}</option>}
      {lessons.map((l) => (
        <option key={l.slug} value={l.slug}>
          {l.title}
        </option>
      ))}
    </select>
  );
}

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: (id: string) => React.ReactNode;
}) {
  const id = useId();
  return (
    <Labelled id={id} label={label} error={error}>
      {children(id)}
    </Labelled>
  );
}
