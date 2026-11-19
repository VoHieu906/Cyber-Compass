"use client";

import { useTranslations } from "next-intl";
import type { ComponentType } from "react";
import type { ZodType } from "zod";
import { Alert } from "@/components/ui/alert";
import { CiaTriad, ciaSchema } from "./cia-triad";
import { OsiModel, osiSchema } from "./osi-model";
import { handshakeSchema, TcpHandshake } from "./tcp-handshake";

type Entry = { schema: ZodType; Component: ComponentType<{ data: never }> };

/**
 * Diagram components by key. The content each one shows comes from the lesson data
 * (validated here); the component only decides how to draw it. Keys must match
 * ContentSeeder::DIAGRAMS on the backend.
 */
const registry: Record<string, Entry> = {
  "osi-model": { schema: osiSchema, Component: OsiModel },
  "tcp-handshake": { schema: handshakeSchema, Component: TcpHandshake },
  "cia-triad": { schema: ciaSchema, Component: CiaTriad },
};

export function Diagram({ diagramKey, data }: { diagramKey: string; data: unknown }) {
  const t = useTranslations("learn");
  const entry = registry[diagramKey];
  const parsed = entry?.schema.safeParse(data);

  if (!entry || !parsed?.success) {
    return <Alert tone="info">{t("diagramUnavailable")}</Alert>;
  }

  const { Component } = entry;
  return <Component data={parsed.data as never} />;
}
