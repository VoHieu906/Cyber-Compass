"use client";

import { Plus, X } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useMemo, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { ArchitectureView } from "@/features/course-map/components/architecture-view";
import { SHAPE_BOX, fitBox } from "@/lib/graph/shapes/sizes";
import {
  SHAPES,
  SHAPE_LIMITS,
  architectureSchema,
  type Architecture,
  type ShapeId,
  type SubNode,
} from "@/lib/graph/shapes/schemas";
import { FILLABLE, fillFromBlock } from "../architecture-fill";
import { errorAt, errorsUnder, moveItem, type FieldErrors } from "../editor-state";
import { useAdminCourses } from "../hooks";
import type { AdminLesson, WorkingCopy } from "../types";
import { ArchitectureSuggestions } from "./architecture-suggestions";
import { Labelled } from "./document-editor";
import { RowControls, StringListEditor } from "./list-editors";

const EMPTY: Architecture = { version: 1, problem: "", shape: null, nodes: [], edges: [] };

/**
 * The lesson's architecture (F3, D57, D59): its one-line problem, its shape and the parts in that
 * shape, each optionally linked to a block of this lesson or to another lesson of the course. It
 * is part of the working copy, so it is drafted, previewed, published and restored with the
 * lesson, and the server checks it against the blocks in the same save. Forms and a live preview
 * with the learner renderer; no drag and drop (D27).
 */
export function ArchitecturePanel({
  copy,
  lesson,
  errors,
  onChange,
}: {
  copy: WorkingCopy;
  lesson: AdminLesson;
  errors: FieldErrors;
  onChange: (patch: Partial<WorkingCopy>) => void;
}) {
  const t = useTranslations("content.editor.architecture");
  const tm = useTranslations("courseMap");
  const courses = useAdminCourses();
  const ids = { problem: useId(), shape: useId(), fill: useId() };
  const [fillFrom, setFillFrom] = useState("");
  const [selected, setSelected] = useState<string | null>(null);
  const arch = copy.architecture;
  const set = (patch: Partial<Architecture>) => onChange({ architecture: { ...(arch ?? EMPTY), ...patch } });
  const err = (path: string) => errorAt(errors, `architecture.${path}`);

  const sections = copy.sections
    .filter((s) => s.id)
    .map((s, i) => ({ id: s.id!, label: s.heading ?? `${t("block")} ${i + 1} (${s.type})` }));
  const sectionIds = new Set(sections.map((s) => s.id));
  const course = courses.data?.find((c) => c.id === lesson.course.id);
  const otherLessons = course?.chapters.flatMap((c) => c.lessons).filter((l) => l.slug !== copy.slug) ?? [];
  const fillable = copy.sections.filter((s) => s.id && FILLABLE[s.type]);

  const parsed = useMemo(() => (arch ? architectureSchema.safeParse(arch) : null), [arch]);
  const box = useMemo(
    () =>
      arch?.shape
        ? fitBox(arch.shape, arch.nodes.length, arch.rows?.length ?? 0, SHAPE_BOX.compact)
        : SHAPE_BOX.compact,
    [arch],
  );

  if (!arch) {
    return (
      <section aria-labelledby="architecture-heading" className="flex flex-col items-start gap-3">
        <h2 id="architecture-heading" className="text-[length:var(--text-lg)]">
          {t("title")}
        </h2>
        <p className="max-w-[60ch] text-sm">{t("intro")}</p>
        <Button type="button" variant="secondary" size="sm" onClick={() => onChange({ architecture: { ...EMPTY } })}>
          <Plus aria-hidden />
          {t("start")}
        </Button>
        <ArchitectureSuggestions lesson={lesson} copy={copy} onUse={(architecture) => onChange({ architecture })} />
      </section>
    );
  }

  const limits = arch.shape ? SHAPE_LIMITS[arch.shape] : null;
  const setNode = (i: number, patch: Partial<SubNode>) =>
    set({ nodes: arch.nodes.map((n, j) => (j === i ? clean({ ...n, ...patch }) : n)) });
  const addNode = () => {
    const taken = new Set(arch.nodes.map((n) => n.id));
    let n = arch.nodes.length + 1;
    while (taken.has(`part-${n}`)) n++;
    const extra: Partial<SubNode> =
      arch.shape === "mesh"
        ? { x: 50, y: 50 }
        : arch.shape === "matrix"
          ? { cell: [0, 0] }
          : arch.shape === "tree" && arch.nodes.length
            ? { parent: arch.nodes[0].id }
            : {};
    set({ nodes: [...arch.nodes, { id: `part-${n}`, label: t("newPart"), ...extra }] });
  };
  const removeNode = (i: number) => {
    const id = arch.nodes[i].id;
    set({
      nodes: arch.nodes
        .filter((_, j) => j !== i)
        .map((n) => (n.parent === id ? clean({ ...n, parent: undefined }) : n)),
      edges: arch.edges.filter((e) => e.from !== id && e.to !== id),
    });
  };
  const changeShape = (shape: ShapeId | null) => {
    // Shape-only fields go with the shape they belong to; the parts and their links stay.
    const nodes = arch.nodes.map((n) =>
      clean({
        ...n,
        parent: shape === "tree" ? n.parent : undefined,
        x: shape === "mesh" ? (n.x ?? 50) : undefined,
        y: shape === "mesh" ? (n.y ?? 50) : undefined,
        cell: shape === "matrix" ? (n.cell ?? [0, 0]) : undefined,
      }),
    );
    onChange({
      architecture: {
        version: 1,
        problem: arch.problem,
        shape,
        nodes: shape ? nodes : [],
        edges: shape ? arch.edges : [],
        ...(shape === "matrix"
          ? { rows: arch.rows ?? [t("rowDefault")], cols: arch.cols ?? [t("colLeft"), t("colRight")] }
          : {}),
      },
    });
  };

  return (
    <section aria-labelledby="architecture-heading" className="flex flex-col gap-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="flex flex-col gap-1">
          <h2 id="architecture-heading" className="text-[length:var(--text-lg)]">
            {t("title")}
          </h2>
          <p className="max-w-[60ch] text-sm">{t("intro")}</p>
        </div>
        <Button type="button" variant="ghost" size="sm" onClick={() => onChange({ architecture: null })}>
          <X aria-hidden />
          {t("remove")}
        </Button>
      </div>
      {errorAt(errors, "architecture") ? <Alert tone="error">{errorAt(errors, "architecture")}</Alert> : null}
      {lesson.architecture_issues.length ? (
        <Alert tone="info">{t("hiddenLinks", { lessons: lesson.architecture_issues.join(", ") })}</Alert>
      ) : null}
      <ArchitectureSuggestions lesson={lesson} copy={copy} onUse={(architecture) => onChange({ architecture })} />

      <Labelled id={ids.problem} label={t("problem")} hint={t("problemHint")} error={err("problem")}>
        <input
          id={ids.problem}
          value={arch.problem}
          maxLength={140}
          onChange={(e) => set({ problem: e.target.value })}
          aria-invalid={Boolean(err("problem"))}
          className={controlClasses(Boolean(err("problem")))}
        />
      </Labelled>

      <div className="grid gap-5 md:grid-cols-2">
        <Labelled
          id={ids.shape}
          label={t("shape")}
          hint={arch.shape ? tm(`shapeHelp.${arch.shape}`) : t("noShapeHint")}
          error={err("shape")}
        >
          <select
            id={ids.shape}
            value={arch.shape ?? ""}
            onChange={(e) => changeShape((e.target.value || null) as ShapeId | null)}
            className={controlClasses(Boolean(err("shape")))}
          >
            <option value="">{t("noShape")}</option>
            {SHAPES.map((s) => (
              <option key={s} value={s}>
                {tm(`shapes.${s}`)}
              </option>
            ))}
          </select>
        </Labelled>
        {fillable.length ? (
          <div className="flex flex-col gap-1.5">
            <label htmlFor={ids.fill} className="text-sm font-medium text-ink">
              {t("fill")}
            </label>
            <div className="flex gap-2">
              <select
                id={ids.fill}
                value={fillFrom}
                onChange={(e) => setFillFrom(e.target.value)}
                className={controlClasses(false)}
              >
                <option value="">{t("fillPick")}</option>
                {fillable.map((s, i) => (
                  <option key={s.id} value={s.id}>
                    {s.heading ?? `${t("block")} ${i + 1}`} ({tm(`shapes.${FILLABLE[s.type]}`)})
                  </option>
                ))}
              </select>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={!fillFrom}
                onClick={() => {
                  const filled = fillFromBlock(
                    copy.sections.find((s) => s.id === fillFrom)!,
                    arch.problem,
                  );
                  if (filled) onChange({ architecture: filled });
                }}
              >
                {t("fillApply")}
              </Button>
            </div>
            <p className="text-sm text-muted">{t("fillHint")}</p>
          </div>
        ) : null}
      </div>

      {arch.shape === "matrix" ? (
        <div className="grid gap-5 md:grid-cols-2">
          <StringListEditor
            label={t("rows")}
            items={arch.rows ?? []}
            onChange={(rows) => set({ rows })}
            errors={errors}
            errorPrefix="architecture.rows"
            maxItems={4}
            addLabel={t("addRow")}
          />
          <StringListEditor
            label={t("cols")}
            items={arch.cols ?? []}
            onChange={(cols) => set({ cols })}
            errors={errors}
            errorPrefix="architecture.cols"
            maxItems={3}
            addLabel={t("addCol")}
          />
        </div>
      ) : null}

      {arch.shape ? (
        <fieldset className="flex min-w-0 flex-col gap-3">
          <legend className="mb-1.5 text-sm font-medium text-ink">
            {t("parts")}{" "}
            {limits ? (
              <span className="font-normal text-muted">({t("partsLimit", { min: limits.min, max: limits.max })})</span>
            ) : null}
          </legend>
          {err("nodes") ? <p className="text-sm text-bad">{err("nodes")}</p> : null}
          <ol className="flex flex-col gap-3">
            {arch.nodes.map((node, i) => {
              const missing = node.section && !sectionIds.has(node.section);
              return (
                <li key={i} className="panel flex flex-col gap-3 p-3">
                  <div className="flex items-start gap-2">
                    <span className="measured mt-2 w-5 shrink-0 text-right text-xs text-muted">{i + 1}</span>
                    <div className="grid min-w-0 flex-1 gap-3 md:grid-cols-2">
                      <Field label={t("partLabel")} error={err(`nodes.${i}.label`) ?? err(`nodes.${i}.id`)}>
                        {(id) => (
                          <input
                            id={id}
                            value={node.label}
                            maxLength={40}
                            onChange={(e) => setNode(i, { label: e.target.value })}
                            className={controlClasses(Boolean(err(`nodes.${i}.label`)))}
                          />
                        )}
                      </Field>
                      <Field label={t("partSection")} error={missing ? t("missingSection") : err(`nodes.${i}.section`)}>
                        {(id) => (
                          <select
                            id={id}
                            value={node.section ?? ""}
                            onChange={(e) => setNode(i, { section: e.target.value || undefined })}
                            className={controlClasses(Boolean(missing || err(`nodes.${i}.section`)))}
                          >
                            <option value="">{t("none")}</option>
                            {missing ? <option value={node.section}>{t("removedBlock")}</option> : null}
                            {sections.map((s) => (
                              <option key={s.id} value={s.id}>
                                {s.label}
                              </option>
                            ))}
                          </select>
                        )}
                      </Field>
                      <Field label={t("partNote")} error={err(`nodes.${i}.note`)}>
                        {(id) => (
                          <textarea
                            id={id}
                            rows={2}
                            value={node.note ?? ""}
                            maxLength={240}
                            onChange={(e) => setNode(i, { note: e.target.value || undefined })}
                            className={controlClasses(Boolean(err(`nodes.${i}.note`)), "h-auto py-2")}
                          />
                        )}
                      </Field>
                      <Field label={t("partLesson")} error={err(`nodes.${i}.lesson`)}>
                        {(id) => (
                          <select
                            id={id}
                            value={node.lesson ?? ""}
                            onChange={(e) => setNode(i, { lesson: e.target.value || undefined })}
                            className={controlClasses(Boolean(err(`nodes.${i}.lesson`)))}
                          >
                            <option value="">{t("none")}</option>
                            {node.lesson && !otherLessons.some((l) => l.slug === node.lesson) ? (
                              <option value={node.lesson}>{node.lesson}</option>
                            ) : null}
                            {otherLessons.map((l) => (
                              <option key={l.slug} value={l.slug}>
                                {l.title}
                              </option>
                            ))}
                          </select>
                        )}
                      </Field>
                      {arch.shape === "tree" ? (
                        <Field label={t("partParent")} error={err(`nodes.${i}.parent`)}>
                          {(id) => (
                            <select
                              id={id}
                              value={node.parent ?? ""}
                              onChange={(e) => setNode(i, { parent: e.target.value || undefined })}
                              className={controlClasses(Boolean(err(`nodes.${i}.parent`)))}
                            >
                              <option value="">{t("root")}</option>
                              {arch.nodes
                                .filter((n) => n.id !== node.id)
                                .map((n) => (
                                  <option key={n.id} value={n.id}>
                                    {n.label}
                                  </option>
                                ))}
                            </select>
                          )}
                        </Field>
                      ) : null}
                      {arch.shape === "mesh" ? (
                        <div className="grid grid-cols-2 gap-3">
                          {(["x", "y"] as const).map((axis) => (
                            <Field key={axis} label={t(axis)} error={err(`nodes.${i}.${axis}`)}>
                              {(id) => (
                                <input
                                  id={id}
                                  type="number"
                                  min={0}
                                  max={100}
                                  value={node[axis] ?? 50}
                                  onChange={(e) => setNode(i, { [axis]: Number(e.target.value) })}
                                  className={controlClasses(false, "measured")}
                                />
                              )}
                            </Field>
                          ))}
                        </div>
                      ) : null}
                      {arch.shape === "matrix" ? (
                        <div className="grid grid-cols-2 gap-3">
                          <Field label={t("row")} error={err(`nodes.${i}.cell`)}>
                            {(id) => (
                              <select
                                id={id}
                                value={node.cell?.[0] ?? 0}
                                onChange={(e) => setNode(i, { cell: [Number(e.target.value), node.cell?.[1] ?? 0] })}
                                className={controlClasses(Boolean(err(`nodes.${i}.cell`)))}
                              >
                                {(arch.rows ?? []).map((r, ri) => (
                                  <option key={ri} value={ri}>
                                    {r}
                                  </option>
                                ))}
                              </select>
                            )}
                          </Field>
                          <Field label={t("col")}>
                            {(id) => (
                              <select
                                id={id}
                                value={node.cell?.[1] ?? 0}
                                onChange={(e) => setNode(i, { cell: [node.cell?.[0] ?? 0, Number(e.target.value)] })}
                                className={controlClasses(false)}
                              >
                                {(arch.cols ?? []).map((c, ci) => (
                                  <option key={ci} value={ci}>
                                    {c}
                                  </option>
                                ))}
                              </select>
                            )}
                          </Field>
                        </div>
                      ) : null}
                    </div>
                    <RowControls
                      index={i}
                      count={arch.nodes.length}
                      label={node.label}
                      onMove={(to) => set({ nodes: moveItem(arch.nodes, i, to) })}
                      onRemove={() => removeNode(i)}
                    />
                  </div>
                </li>
              );
            })}
          </ol>
          <Button
            type="button"
            variant="secondary"
            size="sm"
            className="self-start"
            disabled={!limits || arch.nodes.length >= limits.max}
            onClick={addNode}
          >
            <Plus aria-hidden />
            {t("addPart")}
          </Button>
        </fieldset>
      ) : null}

      {arch.shape && arch.nodes.length > 1 ? (
        <fieldset className="flex min-w-0 flex-col gap-3">
          <legend className="mb-1.5 text-sm font-medium text-ink">{t("connections")}</legend>
          <p className="text-sm text-muted">{t("connectionsHint")}</p>
          <ol className="flex flex-col gap-2">
            {arch.edges.map((edge, i) => (
              <li key={i} className="flex flex-wrap items-end gap-2">
                {(["from", "to"] as const).map((end) => (
                  <Field
                    key={end}
                    label={t(end)}
                    error={err(`edges.${i}.${end}`) ?? (end === "to" ? err(`edges.${i}`) : undefined)}
                  >
                    {(id) => (
                      <select
                        id={id}
                        value={edge[end]}
                        onChange={(e) =>
                          set({ edges: arch.edges.map((x, j) => (j === i ? { ...x, [end]: e.target.value } : x)) })
                        }
                        className={controlClasses(false, "min-w-[10rem]")}
                      >
                        {arch.nodes.map((n) => (
                          <option key={n.id} value={n.id}>
                            {n.label}
                          </option>
                        ))}
                      </select>
                    )}
                  </Field>
                ))}
                <Field label={t("edgeLabel")} error={err(`edges.${i}.label`)}>
                  {(id) => (
                    <input
                      id={id}
                      value={edge.label ?? ""}
                      maxLength={24}
                      onChange={(e) =>
                        set({
                          edges: arch.edges.map((x, j) => (j === i ? { ...x, label: e.target.value || undefined } : x)),
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
                  aria-label={t("removeConnection", { number: i + 1 })}
                  onClick={() => set({ edges: arch.edges.filter((_, j) => j !== i) })}
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
            disabled={arch.edges.length >= 20}
            onClick={() => set({ edges: [...arch.edges, { from: arch.nodes[0].id, to: arch.nodes[1].id }] })}
          >
            <Plus aria-hidden />
            {t("addConnection")}
          </Button>
        </fieldset>
      ) : null}

      <div className="flex flex-col gap-2">
        <h3 className="text-sm font-medium text-ink">{t("preview")}</h3>
        {parsed?.success && parsed.data.shape ? (
          <div className="overflow-x-auto rounded-card border border-rule bg-paper-2 p-3">
            <ArchitectureView
              architecture={{ ...parsed.data, shape: parsed.data.shape }}
              box={box}
              selected={selected}
              onSelect={setSelected}
              label={t("preview")}
            />
          </div>
        ) : parsed && !parsed.success ? (
          <ul className="list-disc pl-5 text-sm text-bad">
            {parsed.error.issues.slice(0, 6).map((issue, i) => (
              <li key={i}>
                {issue.path.length ? `${issue.path.join(".")}: ` : ""}
                {issue.message}
              </li>
            ))}
          </ul>
        ) : (
          <p className="text-sm text-muted">{t("previewProblemOnly")}</p>
        )}
        {Object.keys(errorsUnder(errors, "architecture")).length ? (
          <p className="text-sm text-bad">{t("serverErrors")}</p>
        ) : null}
      </div>
    </section>
  );
}

/** Drops fields set to undefined, so the payload never carries empty keys. */
function clean(node: SubNode): SubNode {
  return Object.fromEntries(Object.entries(node).filter(([, v]) => v !== undefined)) as SubNode;
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
