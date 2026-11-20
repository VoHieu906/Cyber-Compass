"use client";

import { useTranslations } from "next-intl";
import type { ReactNode } from "react";
import { Alert } from "@/components/ui/alert";
import { LayerStack } from "../blocks/layer-stack";
import { Sequence } from "../blocks/sequence";
import { CiaTriad } from "./cia-triad";
import { ciaSchema, handshakeSchema, handshakeToSequence, osiSchema, osiToLayerStack } from "./data";

/**
 * Bespoke diagrams by key. The content comes from the lesson data (validated here); the
 * component only decides how to draw it. The legacy OSI and TCP keys draw through the generic
 * layer-stack and sequence components, so older drafts and revisions still render.
 */
function render(key: string, data: unknown): ReactNode | null {
  switch (key) {
    case "cia-triad": {
      const parsed = ciaSchema.safeParse(data);
      return parsed.success ? <CiaTriad data={parsed.data} /> : null;
    }
    case "osi-model": {
      const parsed = osiSchema.safeParse(data);
      return parsed.success ? <LayerStack data={osiToLayerStack(parsed.data)} /> : null;
    }
    case "tcp-handshake": {
      const parsed = handshakeSchema.safeParse(data);
      return parsed.success ? <Sequence data={handshakeToSequence(parsed.data)} /> : null;
    }
    default:
      return null;
  }
}

export function Diagram({ diagramKey, data }: { diagramKey: string; data: unknown }) {
  const t = useTranslations("learn");
  return render(diagramKey, data) ?? <Alert tone="info">{t("diagramUnavailable")}</Alert>;
}
