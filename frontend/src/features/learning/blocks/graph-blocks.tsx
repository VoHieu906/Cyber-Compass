"use client";

import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { GraphView, type GraphViewEdge, type GraphViewNode } from "@/lib/graph/graph-view";
import type { Flow as FlowData, Tree as TreeData } from "./schemas";

/*
 * flow and tree: auto-laid-out SVG (lib/graph) plus a detail panel for nodes that carry a
 * detail, and a text version listing every node and connection. Loaded on demand (dagre).
 */

function DetailPanel({ title, detail }: { title: string | null; detail: string | null }) {
  const t = useTranslations("learn.blocks");
  return (
    <div className="min-h-14 rounded-control border border-rule bg-paper-2 p-4 text-sm" aria-live="polite">
      {title && detail ? (
        <p className="text-ink">
          <span className="mr-2 font-medium">{title}</span>
          {detail}
        </p>
      ) : (
        <p>{t("selectNode")}</p>
      )}
    </div>
  );
}

function TextVersion({ children }: { children: React.ReactNode }) {
  const t = useTranslations("learn.blocks");
  return (
    <details className="group text-sm">
      <summary className="cursor-pointer rounded-control text-muted hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
        {t("textVersion")}
      </summary>
      <div className="mt-2 rounded-control border border-rule bg-paper-2 p-4">{children}</div>
    </details>
  );
}

export function Flow({ data }: { data: FlowData }) {
  const t = useTranslations("learn.blocks");
  const [selected, setSelected] = useState<string | null>(null);
  const byId = useMemo(() => new Map(data.nodes.map((n) => [n.id, n])), [data.nodes]);
  const nodes = useMemo<GraphViewNode[]>(
    () =>
      data.nodes.map((n) => ({
        id: n.id,
        label: n.label,
        shape: n.kind === "decision" ? "diamond" : n.kind === "start" || n.kind === "end" ? "pill" : "box",
        emphasis: n.kind === "start" || n.kind === "end",
        selectable: Boolean(n.detail),
      })),
    [data.nodes],
  );
  const edges = useMemo<GraphViewEdge[]>(() => data.edges.map((e) => ({ ...e })), [data.edges]);
  const hasDetails = data.nodes.some((n) => n.detail);
  const current = selected ? byId.get(selected) : undefined;

  return (
    <div className="flex flex-col gap-3">
      <GraphView
        nodes={nodes}
        edges={edges}
        direction={data.direction === "right" ? "LR" : "TB"}
        selected={selected}
        onSelect={(id) => setSelected((s) => (s === id ? null : id))}
        label={t("flowLabel", { count: data.nodes.length })}
      />
      {hasDetails ? <DetailPanel title={current?.label ?? null} detail={current?.detail ?? null} /> : null}
      <TextVersion>
        <ol className="flex list-decimal flex-col gap-2 pl-5">
          {data.nodes.map((n) => {
            const out = data.edges.filter((e) => e.from === n.id);
            return (
              <li key={n.id}>
                <span className="text-ink">{n.label}</span>
                {n.kind === "decision" ? <span className="text-muted"> ({t("decision")})</span> : null}
                {n.detail ? <span className="block">{n.detail}</span> : null}
                {out.length ? (
                  <ul className="mt-1 flex flex-col gap-0.5">
                    {out.map((e, i) => (
                      <li key={i}>
                        {e.label ? `${e.label}: ` : ""}
                        {t("goesTo", { target: byId.get(e.to)?.label ?? e.to })}
                      </li>
                    ))}
                  </ul>
                ) : null}
              </li>
            );
          })}
        </ol>
      </TextVersion>
    </div>
  );
}

type TreeNode = TreeData["nodes"][number];

function Outline({ node, childrenOf }: { node: TreeNode; childrenOf: Map<string, TreeNode[]> }) {
  const kids = childrenOf.get(node.id) ?? [];
  return (
    <li>
      <span className="text-ink">{node.label}</span>
      {node.note ? <span className="measured text-muted"> {node.note}</span> : null}
      {node.detail ? <span className="block">{node.detail}</span> : null}
      {kids.length ? (
        <ul className="mt-1 flex flex-col gap-1 border-l border-rule pl-4">
          {kids.map((k) => (
            <Outline key={k.id} node={k} childrenOf={childrenOf} />
          ))}
        </ul>
      ) : null}
    </li>
  );
}

export function Tree({ data }: { data: TreeData }) {
  const t = useTranslations("learn.blocks");
  const [selected, setSelected] = useState<string | null>(null);
  const byId = useMemo(() => new Map(data.nodes.map((n) => [n.id, n])), [data.nodes]);
  const childrenOf = useMemo(() => {
    const map = new Map<string, TreeNode[]>();
    for (const n of data.nodes) if (n.parent) map.set(n.parent, [...(map.get(n.parent) ?? []), n]);
    return map;
  }, [data.nodes]);
  const nodes = useMemo<GraphViewNode[]>(
    () =>
      data.nodes.map((n) => ({
        id: n.id,
        label: n.label,
        note: n.note,
        emphasis: !n.parent,
        selectable: Boolean(n.detail),
      })),
    [data.nodes],
  );
  const edges = useMemo<GraphViewEdge[]>(
    () => data.nodes.filter((n) => n.parent).map((n) => ({ from: n.parent!, to: n.id })),
    [data.nodes],
  );
  const root = data.nodes.find((n) => !n.parent)!;
  const current = selected ? byId.get(selected) : undefined;

  return (
    <div className="flex flex-col gap-3">
      <GraphView
        nodes={nodes}
        edges={edges}
        arrows={false}
        selected={selected}
        onSelect={(id) => setSelected((s) => (s === id ? null : id))}
        label={t("treeLabel", { root: root.label })}
      />
      {data.nodes.some((n) => n.detail) ? (
        <DetailPanel title={current?.label ?? null} detail={current?.detail ?? null} />
      ) : null}
      <TextVersion>
        <ul>
          <Outline node={root} childrenOf={childrenOf} />
        </ul>
      </TextVersion>
    </div>
  );
}
