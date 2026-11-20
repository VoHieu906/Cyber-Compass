import type { CourseProgress, LessonStatus } from "@/features/catalog/types";

export type CalloutTone = "info" | "tip" | "warning";

export type LessonSection =
  | { id: string; type: "prose"; heading: string | null; payload: { text: string } }
  | { id: string; type: "callout"; heading: string | null; payload: { text: string; tone: CalloutTone } }
  | { id: string; type: "steps"; heading: string | null; payload: { items: string[] } }
  | { id: string; type: "code"; heading: string | null; payload: { code: string; language: string } }
  | {
      id: string;
      type: "diagram";
      heading: string | null;
      payload: { key: string; caption: string | null; data: unknown };
    }
  | {
      id: string;
      type: "media";
      heading: string | null;
      /** url, alt and size are resolved by the API from the uploaded image; url is null if it was removed. */
      payload: {
        media: string;
        caption?: string | null;
        url: string | null;
        alt: string;
        width: number | null;
        height: number | null;
      };
    }
  | {
      id: string;
      /** Interactive 3D, drawn in the sandbox (docs/SANDBOXED_3D.md). script-3d payloads may arrive with disabled: true. */
      type: "scene-3d" | "script-3d";
      heading: string | null;
      payload: Record<string, unknown>;
    }
  | {
      id: string;
      /** The generic 2D blocks; each payload is checked against its zod contract when rendered (blocks/schemas.ts). */
      type: VisualBlockType;
      heading: string | null;
      payload: Record<string, unknown>;
    };

export type VisualBlockType =
  | "layer-stack"
  | "sequence"
  | "flow"
  | "tree"
  | "topology"
  | "comparison"
  | "timeline"
  | "bitfield"
  | "calculator"
  | "step-through"
  | "knowledge-check";

export type LessonLink = { slug: string; title: string };

export type Lesson = {
  slug: string;
  title: string;
  summary: string;
  estimated_minutes: number;
  objectives: string[];
  key_takeaways: string[];
  resources: { label: string; url: string }[];
  course: { slug: string; title: string; domain: string };
  chapter: { title: string; position: number };
  prerequisites: LessonLink[];
  sections: LessonSection[];
  navigation: { position: number; total: number; previous: LessonLink | null; next: LessonLink | null };
  progress: { status: LessonStatus; completed_at: string | null };
};

export type CompleteLessonResult = {
  status: LessonStatus;
  completed_at: string | null;
  course: CourseProgress;
};
