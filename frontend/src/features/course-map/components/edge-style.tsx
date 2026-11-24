import { useTranslations } from "next-intl";
import type { SVGProps } from "react";
import type { EdgeType, ShapeId } from "@/lib/graph/shapes/schemas";
import { cn } from "@/lib/utils";
import { ShapeGlyph } from "./shape-glyph";

/*
 * How every line on the course map is drawn (UI Part 4, DESIGN_SYSTEM "Course map"): one style
 * per kind of relation, read by the map, the focus panel (also the admin architecture preview),
 * the legend and the arrowheads, so they can never drift apart. Colour says the scope (between
 * lessons, inside a lesson, from a part to a lesson); weight, line and arrowheads say the kind,
 * so no two kinds differ by colour alone. Every colour is a token at 3:1 or more against the
 * canvas, the lane bands and the panel, in both themes.
 */

export type EdgeKind = EdgeType | "contributes" | "part-relation" | "part-order" | "part-connector" | "part-link";
export type EdgeScope = "lesson" | "part" | "link";
type Head = "filled" | "open" | "dot";
type Line = "solid" | "dashed" | "dotted" | "double";

export type EdgeStyle = {
  scope: EdgeScope;
  color: string;
  width: number;
  line: Line;
  start?: Head;
  end?: Head;
  /** Smaller arrowheads, for short lines inside a lesson and the quiet lines into the goal. */
  small?: boolean;
};

const LIGHT = 1.5;
const HEAVY = 2.25;
const INK = "var(--color-ink-2)";
const QUIET = "var(--color-muted)";

export const EDGE_STYLES: Record<EdgeKind, EdgeStyle> = {
  requires: { scope: "lesson", color: INK, width: HEAVY, line: "solid", end: "filled" },
  "builds-on": { scope: "lesson", color: INK, width: LIGHT, line: "solid", end: "open" },
  "feeds-into": { scope: "lesson", color: INK, width: LIGHT, line: "dashed", end: "filled" },
  "contrasts-with": { scope: "lesson", color: INK, width: LIGHT, line: "dotted", start: "open", end: "open" },
  "parallel-to": { scope: "lesson", color: INK, width: LIGHT, line: "double" },
  contributes: { scope: "lesson", color: QUIET, width: 1, line: "dotted", end: "open", small: true },
  "part-relation": {
    scope: "part",
    color: "var(--color-attn)",
    width: LIGHT,
    line: "solid",
    end: "filled",
    small: true,
  },
  "part-order": { scope: "part", color: QUIET, width: LIGHT, line: "solid", end: "open", small: true },
  "part-connector": { scope: "part", color: QUIET, width: LIGHT, line: "solid" },
  "part-link": {
    scope: "link",
    color: "var(--color-accent)",
    width: LIGHT,
    line: "dashed",
    start: "dot",
    end: "filled",
  },
};

/** Legend order: the scopes, and each scope's kinds. */
export const SCOPES: { scope: EdgeScope; kinds: EdgeKind[] }[] = [
  { scope: "lesson", kinds: ["requires", "builds-on", "feeds-into", "contrasts-with", "parallel-to", "contributes"] },
  { scope: "part", kinds: ["part-relation", "part-order", "part-connector"] },
  { scope: "link", kinds: ["part-link"] },
];

const DASH: Record<Line, Pick<SVGProps<SVGPathElement>, "strokeDasharray" | "strokeLinecap">> = {
  solid: {},
  dashed: { strokeDasharray: "5 4", strokeLinecap: "butt" },
  dotted: { strokeDasharray: "0 4", strokeLinecap: "round" },
  double: {},
};

const markerId = (prefix: string, head: Head, small?: boolean) => `${prefix}-${head}${small ? "-s" : ""}`;

/**
 * One line of a kind. `emphasis` makes it 0.75px heavier in its own colour (a hover, a focus, a
 * legend row); `heads` drops one end's head where a line is drawn in two pieces (a part link).
 * `gap` is the surface under a double line, which shows between its two strokes.
 */
export function EdgePath({
  kind,
  d,
  prefix,
  emphasis = false,
  heads = "both",
  gap = "var(--color-paper-3)",
  className,
  style,
  ...rest
}: {
  kind: EdgeKind;
  d: string;
  /** The id prefix of the EdgeMarkers in the same document. */
  prefix: string;
  emphasis?: boolean;
  heads?: "both" | "start" | "end" | "none";
  gap?: string;
} & Omit<SVGProps<SVGPathElement>, "d">) {
  const s = EDGE_STYLES[kind];
  const width = s.width + (emphasis ? 0.75 : 0);
  if (s.line === "double")
    return (
      <>
        <path
          d={d}
          fill="none"
          strokeWidth={width + 3.5}
          className={className}
          data-kind={kind}
          {...rest}
          style={{ ...style, stroke: s.color }}
        />
        <path d={d} fill="none" strokeWidth={width} className={className} style={{ ...style, stroke: gap }} />
      </>
    );
  return (
    <path
      d={d}
      fill="none"
      strokeWidth={width}
      {...DASH[s.line]}
      markerStart={
        s.start && (heads === "both" || heads === "start") ? `url(#${markerId(prefix, s.start, s.small)})` : undefined
      }
      markerEnd={
        s.end && (heads === "both" || heads === "end") ? `url(#${markerId(prefix, s.end, s.small)})` : undefined
      }
      className={className}
      data-kind={kind}
      {...rest}
      // Last, so a caller's style (an opacity) never replaces the kind's colour.
      style={{ ...style, stroke: s.color }}
    />
  );
}

/** Every arrowhead, in the colour of the line that uses it, at a fixed size whatever the line's weight. */
export function EdgeMarkers({ prefix }: { prefix: string }) {
  return (
    <defs>
      {[false, true].map((small) => {
        const size = small ? 7 : 10;
        const common = {
          viewBox: "0 0 10 10",
          markerUnits: "userSpaceOnUse",
          markerWidth: size,
          markerHeight: size,
          orient: "auto-start-reverse",
        } as const;
        return [
          <marker key={`f${small}`} id={markerId(prefix, "filled", small)} refX="9" refY="5" {...common}>
            <path d="M 0 0 L 10 5 L 0 10 z" style={{ fill: "context-stroke" }} />
          </marker>,
          <marker key={`o${small}`} id={markerId(prefix, "open", small)} refX="9" refY="5" {...common}>
            <path
              d="M 1 1 L 9 5 L 1 9"
              fill="none"
              strokeWidth={1.6}
              strokeLinecap="round"
              style={{ stroke: "context-stroke" }}
            />
          </marker>,
          <marker key={`d${small}`} id={markerId(prefix, "dot", small)} refX="5" refY="5" {...common}>
            <circle cx="5" cy="5" r="3.5" style={{ fill: "context-stroke" }} />
          </marker>,
        ];
      })}
    </defs>
  );
}

const LEGEND = "cmlg";

/**
 * What the lines and shape glyphs on this map mean, grouped by scope, listing only the kinds the
 * map draws. A row previews its kind on the map while hovered or focused; a click keeps it
 * (aria-pressed) until it is clicked again or Escape is pressed.
 */
export function EdgeLegend({
  kinds,
  shapes,
  active,
  pinned,
  onPreview,
  onPin,
}: {
  kinds: EdgeKind[];
  shapes: ShapeId[];
  active: EdgeKind | null;
  pinned: EdgeKind | null;
  onPreview: (kind: EdgeKind | null) => void;
  onPin: (kind: EdgeKind | null) => void;
}) {
  const t = useTranslations("courseMap");
  const groups = SCOPES.map((g) => ({ ...g, kinds: g.kinds.filter((k) => kinds.includes(k)) })).filter(
    (g) => g.kinds.length,
  );
  return (
    <div className="@container flex flex-col gap-3 text-sm">
      <svg width={0} height={0} className="absolute" aria-hidden>
        <EdgeMarkers prefix={LEGEND} />
      </svg>
      <h3 className="text-sm font-medium text-ink">{t("legend")}</h3>
      <p className="text-muted">{t("legendHint")}</p>
      <div className="grid gap-4 @xl:grid-cols-2">
        <div className="flex flex-col gap-3">
          {groups.map((g) => (
            <div key={g.scope} className="flex flex-col gap-1">
              <p className="text-muted">{t(`legendScopes.${g.scope}`)}</p>
              <ul className="flex flex-col">
                {g.kinds.map((kind) => (
                  <li key={kind}>
                    <button
                      type="button"
                      aria-pressed={pinned === kind}
                      data-legend={kind}
                      onPointerEnter={() => onPreview(kind)}
                      onPointerLeave={() => onPreview(null)}
                      onFocus={() => onPreview(kind)}
                      onBlur={() => onPreview(null)}
                      onClick={() => onPin(pinned === kind ? null : kind)}
                      onKeyDown={(e) => {
                        if (e.key === "Escape" && pinned) {
                          e.stopPropagation();
                          onPin(null);
                        }
                      }}
                      className={cn(
                        "flex min-h-8 w-full items-center gap-2 rounded-control px-1.5 text-left text-ink-2 transition-colors duration-[var(--dur-micro)] hover:bg-paper-3",
                        active === kind && "bg-paper-3 text-ink",
                        pinned === kind && "ring-1 ring-rule-2",
                      )}
                    >
                      <svg width={48} height={14} aria-hidden className="shrink-0 overflow-visible">
                        <EdgePath kind={kind} d="M 4 7 H 42" prefix={LEGEND} gap="var(--color-paper)" />
                      </svg>
                      {t(`edges.${kind}`)}
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ))}
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

/** The kind each part-to-part line of a shape layout is. */
export function partKind(edge: { implicit: boolean; directed: boolean }): EdgeKind {
  return !edge.implicit ? "part-relation" : edge.directed ? "part-order" : "part-connector";
}
