import type { EdgeType, ShapeId, SubNode } from "@/lib/graph/shapes/schemas";

/** `graph` on GET /courses/{slug} (F3, D57): published lessons only, filtered on the server. */
export type CourseMapData = {
  problem: string;
  layout: "auto" | "manual";
  lanes: { id: string; label: string }[];
  lessons: MapLesson[];
  edges: MapEdge[];
};

export type MapLesson = {
  slug: string;
  title: string;
  placement: { col: number; row: number; lane?: string } | null;
  problem: string | null;
  shape: ShapeId | null;
  rows: string[] | null;
  cols: string[] | null;
  nodes: SubNode[];
  edges: { from: string; to: string; label?: string }[];
};

export type MapEdge = { from: string; to: string; type: EdgeType; label?: string };
