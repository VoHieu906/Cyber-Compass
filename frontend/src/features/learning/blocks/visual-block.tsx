"use client";

import dynamic from "next/dynamic";
import { useTranslations } from "next-intl";
import type { ComponentType } from "react";
import type { ZodType } from "zod";
import { Alert } from "@/components/ui/alert";
import { Calculator } from "./calculator";
import { KnowledgeCheck } from "./knowledge-check";
import { LayerStack } from "./layer-stack";
import {
  bitfieldSchema,
  calculatorSchema,
  comparisonSchema,
  flowSchema,
  knowledgeCheckSchema,
  layerStackSchema,
  sequenceSchema,
  stepThroughSchema,
  timelineSchema,
  topologySchema,
  treeSchema,
} from "./schemas";
import { Sequence } from "./sequence";
import { Bitfield, Comparison, StepThrough, Timeline } from "./simple-blocks";
import { Topology } from "./topology";

function GraphLoading() {
  return <div className="h-48 rounded-control border border-rule bg-paper-3 motion-safe:animate-pulse" />;
}

// The graph blocks carry the layout engine (dagre); load it only for lessons that use them.
const Flow = dynamic(() => import("./graph-blocks").then((m) => m.Flow), { loading: GraphLoading });
const Tree = dynamic(() => import("./graph-blocks").then((m) => m.Tree), { loading: GraphLoading });

// eslint-disable-next-line @typescript-eslint/no-explicit-any -- each entry pairs a schema with the component for its output
type Entry = { schema: ZodType; Component: ComponentType<{ data: any }> };

const visualBlocks: Record<string, Entry> = {
  "layer-stack": { schema: layerStackSchema, Component: LayerStack },
  sequence: { schema: sequenceSchema, Component: Sequence },
  flow: { schema: flowSchema, Component: Flow },
  tree: { schema: treeSchema, Component: Tree },
  topology: { schema: topologySchema, Component: Topology },
  comparison: { schema: comparisonSchema, Component: Comparison },
  timeline: { schema: timelineSchema, Component: Timeline },
  bitfield: { schema: bitfieldSchema, Component: Bitfield },
  calculator: { schema: calculatorSchema, Component: Calculator },
  "step-through": { schema: stepThroughSchema, Component: StepThrough },
  "knowledge-check": { schema: knowledgeCheckSchema, Component: KnowledgeCheck },
};

export const VISUAL_BLOCK_TYPES = Object.keys(visualBlocks);

export function isVisualBlock(type: string): boolean {
  return type in visualBlocks;
}

/**
 * Renders a generic block after checking its payload against the shared contract. A payload
 * that fails (an older client meeting newer content, say) shows a notice instead of breaking
 * the lesson. The editor's live preview uses the same component.
 */
export function VisualBlock({ type, payload }: { type: string; payload: unknown }) {
  const t = useTranslations("learn");
  const entry = visualBlocks[type];
  const parsed = entry?.schema.safeParse(payload);
  if (!entry || !parsed?.success) return <Alert tone="info">{t("diagramUnavailable")}</Alert>;
  const { Component } = entry;
  return <Component data={parsed.data} />;
}
