import { useTranslations } from "next-intl";
import type { SVGProps } from "react";
import type { EdgeType, ShapeId } from "@/lib/graph/shapes/schemas";
import { ShapeGlyph } from "./shape-glyph";

/*
 * How each relation is drawn (DESIGN_SYSTEM "Course map"): a line style and arrowheads per type,
 * never colour alone, so the legend and the lines read the same in both themes and in print.
 */

type Style = Pick<
  SVGProps<SVGPathElement>,
  "strokeWidth" | "strokeDasharray" | "strokeLinecap" | "markerEnd" | "markerStart" | "style"
>;

const line = { stroke: "var(--color-ink-2)" };

export const EDGE_STYLE: Record<EdgeType | "contributes", Style> = {
  requires: { strokeWidth: 1.75, markerEnd: "url(#cm-solid)", style: line },
  "builds-on": { strokeWidth: 1.25, markerEnd: "url(#cm-open)", style: line },
  "feeds-into": { strokeWidth: 1.5, strokeDasharray: "7 4", markerEnd: "url(#cm-solid)", style: line },
  "contrasts-with": {
    strokeWidth: 1.75,
    strokeDasharray: "1 4",
    strokeLinecap: "round",
    markerEnd: "url(#cm-open)",
    markerStart: "url(#cm-open)",
    style: line,
  },
  // Drawn as a double line: a wide stroke under a thin stroke in the background colour.
  "parallel-to": { strokeWidth: 1.5, style: line },
  contributes: {
    strokeWidth: 1,
    strokeDasharray: "2 5",
    markerEnd: "url(#cm-open)",
    style: { stroke: "var(--color-rule-2)" },
  },
};

export function EdgeMarkers() {
  return (
    <defs>
      <marker
        id="cm-solid"
        viewBox="0 0 10 10"
        refX="9"
        refY="5"
        markerWidth="7"
        markerHeight="7"
        orient="auto-start-reverse"
      >
        <path d="M 0 0 L 10 5 L 0 10 z" style={{ fill: "context-stroke" }} />
      </marker>
      <marker
        id="cm-open"
        viewBox="0 0 10 10"
        refX="9"
        refY="5"
        markerWidth="8"
        markerHeight="8"
        orient="auto-start-reverse"
      >
        <path d="M 1 1 L 9 5 L 1 9" fill="none" strokeWidth={1.5} style={{ stroke: "context-stroke" }} />
      </marker>
    </defs>
  );
}

function Sample({ type }: { type: EdgeType | "contributes" }) {
  return (
    <svg width={44} height={14} aria-hidden className="shrink-0 overflow-visible">
      {type === "parallel-to" ? (
        <path d="M 2 7 H 40" strokeWidth={5} style={{ stroke: "var(--color-rule-2)" }} />
      ) : null}
      <path
        d="M 2 7 H 40"
        fill="none"
        {...EDGE_STYLE[type]}
        {...(type === "parallel-to" ? { style: { stroke: "var(--color-paper)" } } : {})}
      />
    </svg>
  );
}

/** What the line styles and shape glyphs on this map mean. Its samples use the map's markers. */
export function EdgeLegend({ types, shapes }: { types: EdgeType[]; shapes: ShapeId[] }) {
  const t = useTranslations("courseMap");
  return (
    <div className="@container flex flex-col gap-3 text-sm">
      <h3 className="text-sm font-medium text-ink">{t("legend")}</h3>
      <div className="grid gap-4 @xl:grid-cols-2">
        <div className="flex flex-col gap-1.5">
          <p className="text-muted">{t("legendLines")}</p>
          <ul className="flex flex-col gap-1">
            {[...types, "contributes" as const].map((type) => (
              <li key={type} className="flex items-center gap-2 text-ink-2">
                <Sample type={type} />
                {t(`edges.${type}`)}
              </li>
            ))}
          </ul>
        </div>
        {shapes.length ? (
          <div className="flex flex-col gap-1.5">
            <p className="text-muted">{t("legendShapes")}</p>
            <ul className="flex flex-col gap-1">
              {shapes.map((shape) => (
                <li key={shape} className="flex items-center gap-2 text-ink-2">
                  <ShapeGlyph shape={shape} className="text-accent" />
                  <span>
                    <span className="text-ink">{t(`shapes.${shape}`)}</span>: {t(`shapeHelp.${shape}`)}
                  </span>
                </li>
              ))}
            </ul>
          </div>
        ) : null}
      </div>
    </div>
  );
}
