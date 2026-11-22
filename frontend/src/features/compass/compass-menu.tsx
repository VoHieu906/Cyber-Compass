"use client";

import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useId, useLayoutEffect, useMemo, useRef, useState, type KeyboardEvent, type RefObject } from "react";
import { layoutChildRing, layoutRing, placeCompass, ringRadii, type Segment } from "@/lib/radial/layout";
import { ACTIONS, matches, type ActionId } from "@/lib/shortcuts/registry";
import { cn } from "@/lib/utils";
import { useCompassItems, type CompassItem } from "./use-compass-items";

const HUB_R = 58;
const RING = { width: 92, gap: 6 };
const CHILD_RING = { width: 70, gap: 6 };
const GAP_DEG = 1.5;
const MIN_CHILD_DEG = 32;
/** Above this many items a ring shows icons only; the hub and a tooltip give the name. */
export const ICON_ONLY_ABOVE = 10;
const HOVER_INTENT_MS = 120;
const EDGE = 8;

type Level = { items: CompassItem[]; segments: Segment[]; innerR: number; outerR: number };
type Focus = { level: number; index: number };

/** Radii of ring `level`: the top ring is wider than its submenus. */
function radii(level: number) {
  if (level === 0) return ringRadii(0, { hubR: HUB_R, ...RING });
  const first = ringRadii(0, { hubR: HUB_R, ...RING }).outerR;
  return ringRadii(level - 1, { hubR: first, ...CHILD_RING });
}

const depthOf = (items: CompassItem[]): number => (items.length === 0 ? 0 : 1 + Math.max(...items.map((i) => depthOf(i.children ?? []))));

/** Rings for the open chain: the top level, then each opened parent's children around its bearing. */
export function buildLevels(items: CompassItem[], chain: number[]): Level[] {
  const levels: Level[] = [];
  const { innerR, outerR } = radii(0);
  levels.push({ items, innerR, outerR, segments: layoutRing(items.length, { innerR, outerR, gapDeg: GAP_DEG, startDeg: -180 / Math.max(1, items.length) }) });
  for (let l = 0; l < chain.length; l++) {
    const parent = levels[l].items[chain[l]];
    if (!parent?.children?.length) break;
    const r = radii(l + 1);
    const segments = layoutChildRing(parent.children.length, levels[l].segments[chain[l]].centerDeg, { ...r, gapDeg: GAP_DEG, minChildDeg: MIN_CHILD_DEG });
    levels.push({ items: parent.children, segments, ...r });
  }
  return levels;
}

const hit = (e: KeyboardEvent, id: ActionId) => ACTIONS[id].defaults.some((b) => matches(e.nativeEvent, b));

/** The menu's keyboard model (WAI-ARIA menu pattern), pure so it can be tested on its own. */
export function nextFocus(e: KeyboardEvent, levels: Level[], chain: number[], focus: Focus): { chain: number[]; focus: Focus } | "activate" | "close" | null {
  const ring = levels[focus.level].items;
  const n = ring.length;
  const at = (index: number) => ({ chain, focus: { level: focus.level, index: ((index % n) + n) % n } });
  if (hit(e, "compass.next")) return at(focus.index + 1);
  if (hit(e, "compass.previous")) return at(focus.index - 1);
  if (hit(e, "compass.first")) return at(0);
  if (hit(e, "compass.last")) return at(n - 1);
  const back = { chain: chain.slice(0, focus.level - 1), focus: { level: focus.level - 1, index: chain[focus.level - 1] } };
  if (hit(e, "compass.back")) return focus.level > 0 ? back : null;
  if (hit(e, "compass.close")) return focus.level > 0 ? back : "close";
  if (hit(e, "compass.enter")) {
    if (ring[focus.index].children?.length) return { chain: [...chain.slice(0, focus.level), focus.index], focus: { level: focus.level + 1, index: 0 } };
    return "activate";
  }
  if (e.ctrlKey || e.altKey || e.metaKey) return null;
  if (/^Digit[1-9]$/.test(e.code)) {
    const index = Number(e.code.slice(5)) - 1;
    return index < n ? at(index) : null;
  }
  // Type-ahead: the next item whose name starts with the letter.
  if (e.key.length === 1 && /\p{L}/u.test(e.key)) {
    const letter = e.key.toLocaleLowerCase();
    for (let step = 1; step <= n; step++) {
      const index = (focus.index + step) % n;
      if (ring[index].label.toLocaleLowerCase().startsWith(letter)) return at(index);
    }
  }
  return null;
}

/** Label text that fits the segment's width at its radius (about 6.4px per character). */
function fit(label: string, segment: Segment, mid: number) {
  const sweep = Math.min(180, segment.endDeg - segment.startDeg);
  const width = 2 * mid * Math.sin((sweep * Math.PI) / 360) - 10;
  const chars = Math.max(3, Math.floor(width / 6.4));
  return label.length <= chars ? label : `${label.slice(0, chars - 1)}…`;
}

export default function CompassMenu({ id, triggerRef, onClose }: { id: string; triggerRef: RefObject<HTMLButtonElement | null>; onClose: () => void }) {
  const t = useTranslations("compass");
  const router = useRouter();
  const items = useCompassItems();
  const baseId = useId();
  const [chain, setChain] = useState<number[]>([]);
  // Start on the current page's segment, so the needle shows where the learner is.
  const [focus, setFocus] = useState<Focus>(() => ({ level: 0, index: Math.max(0, items.findIndex((i) => i.current)) }));
  const [hovered, setHovered] = useState<Focus | null>(null);
  const [place, setPlace] = useState<{ x: number; y: number; scale: number } | null>(null);
  const svgRef = useRef<SVGSVGElement>(null);
  const intent = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

  const levels = useMemo(() => buildLevels(items, chain), [items, chain]);
  const radius = radii(Math.max(0, depthOf(items) - 1)).outerR + EDGE;

  // Centre as close to the trigger as fits below the header, toward the middle of the screen.
  useLayoutEffect(() => {
    const measure = () => {
      const button = triggerRef.current?.getBoundingClientRect();
      const header = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--header-height")) || 64;
      const anchor = button ? { x: button.left + button.width / 2, y: button.top + button.height / 2 } : { x: innerWidth / 2, y: innerHeight / 2 };
      setPlace(placeCompass(anchor, radius, { left: EDGE, top: header + EDGE, right: innerWidth - EDGE, bottom: innerHeight - EDGE }));
    };
    measure();
    addEventListener("resize", measure);
    return () => removeEventListener("resize", measure);
  }, [radius, triggerRef]);

  // Focus follows the keyboard position (roving tabindex); it never leaves the menu while open.
  useEffect(() => {
    svgRef.current?.querySelector<SVGElement>(`[data-level="${focus.level}"][data-index="${focus.index}"]`)?.focus();
  }, [focus, place]);

  // Outside click closes; the trigger handles its own clicks.
  useEffect(() => {
    const onDown = (e: PointerEvent) => {
      const target = e.target as Node;
      if (svgRef.current?.contains(target) || triggerRef.current?.contains(target)) return;
      onClose();
    };
    document.addEventListener("pointerdown", onDown);
    return () => document.removeEventListener("pointerdown", onDown);
  }, [onClose, triggerRef]);

  useEffect(() => () => clearTimeout(intent.current), []);

  const activate = useCallback(
    (item: CompassItem) => {
      onClose();
      if (item.href) router.push(item.href);
      // After the menu has closed and focus is back on the trigger, so an action can move focus.
      else if (item.run) requestAnimationFrame(() => item.run!());
    },
    [onClose, router],
  );

  function choose(level: number, index: number) {
    const item = levels[level].items[index];
    setFocus({ level, index });
    if (item.children?.length && chain[level] !== index) {
      // First tap opens the submenu; a second tap on a parent with its own page goes there.
      setChain([...chain.slice(0, level), index]);
      return;
    }
    if (item.children?.length && !item.href) return;
    activate(item);
  }

  function hover(level: number, index: number) {
    setHovered({ level, index });
    clearTimeout(intent.current);
    // Hover intent: a diagonal move across a sibling on the way to a submenu changes nothing.
    intent.current = setTimeout(() => {
      const item = levels[level]?.items[index];
      if (!item) return;
      setChain((c) => (item.children?.length ? [...c.slice(0, level), index] : c.slice(0, level)));
      // The keyboard continues from where the pointer settled, so its ring always exists.
      setFocus({ level, index });
    }, HOVER_INTENT_MS);
  }

  function onKeyDown(e: KeyboardEvent<SVGSVGElement>) {
    const next = nextFocus(e, levels, chain, focus);
    if (next === null) return;
    e.preventDefault();
    e.stopPropagation();
    setHovered(null);
    if (next === "close") return onClose();
    if (next === "activate") return activate(levels[focus.level].items[focus.index]);
    setChain(next.chain);
    setFocus(next.focus);
  }

  if (!place) return null;

  const shown = hovered && levels[hovered.level]?.items[hovered.index] ? hovered : focus;
  const shownLevel = levels[shown.level] ?? levels[0];
  const shownItem = shownLevel.items[shown.index];
  const needle = shownLevel.segments[shown.index]?.centerDeg ?? 0;
  const size = 2 * radius * place.scale;
  const position = (f: Focus, item: CompassItem) =>
    [item.label, t("position", { index: f.index + 1, count: levels[f.level].items.length }), item.children?.length ? t("hasSubmenu") : null, item.badge ? t("unread", { count: item.badge }) : null]
      .filter(Boolean)
      .join(", ");

  return (
    <svg
      ref={svgRef}
      id={id}
      viewBox={`${-radius} ${-radius} ${2 * radius} ${2 * radius}`}
      width={size}
      height={size}
      onKeyDown={onKeyDown}
      onPointerLeave={() => {
        clearTimeout(intent.current);
        setHovered(null);
      }}
      className="compass-in pointer-events-none fixed z-[var(--z-sticky)] select-none overflow-visible"
      style={{ left: place.x - size / 2, top: place.y - size / 2 }}
    >
      {levels.map((level, l) => {
        const iconOnly = level.items.length > ICON_ONLY_ABOVE;
        const mid = (level.innerR + level.outerR) / 2;
        const parentId = l > 0 ? `${baseId}-${l - 1}-${chain[l - 1]}` : undefined;
        return (
          <g key={l} role="menu" id={`${baseId}-ring-${l}`} aria-label={l === 0 ? t("label") : undefined} aria-labelledby={parentId} className={cn(l > 0 && "compass-in")}>
            {level.items.map((item, i) => {
              const seg = level.segments[i];
              const isOpen = chain[l] === i && l < levels.length - 1;
              const isFocus = focus.level === l && focus.index === i;
              const iconY = iconOnly ? seg.anchor.y : seg.anchor.y - 8;
              return (
                <g
                  key={item.id}
                  id={`${baseId}-${l}-${i}`}
                  role="menuitem"
                  tabIndex={isFocus ? 0 : -1}
                  data-level={l}
                  data-index={i}
                  aria-label={position({ level: l, index: i }, item)}
                  aria-haspopup={item.children?.length ? "menu" : undefined}
                  aria-expanded={item.children?.length ? isOpen : undefined}
                  aria-controls={isOpen ? `${baseId}-ring-${l + 1}` : undefined}
                  aria-current={item.current ? (item.href ? "page" : "location") : undefined}
                  data-open={isOpen || undefined}
                  onPointerEnter={() => hover(l, i)}
                  // Focus can also arrive from assistive technology; the keyboard continues from there.
                  onFocus={() => {
                    if (!isFocus) setFocus({ level: l, index: i });
                  }}
                  onClick={() => choose(l, i)}
                  className="compass-item pointer-events-auto cursor-pointer outline-none"
                >
                  <title>{item.label}</title>
                  <path d={seg.path} className="compass-seg" />
                  <item.Icon aria-hidden x={seg.anchor.x - 9} y={iconY - 9} width={18} height={18} className="compass-icon" />
                  {iconOnly ? null : (
                    <text x={seg.anchor.x} y={seg.anchor.y + 14} textAnchor="middle" className="compass-text">
                      {fit(item.label, seg, mid)}
                    </text>
                  )}
                  {item.badge ? (
                    <g aria-hidden>
                      <circle cx={seg.anchor.x + 11} cy={iconY - 9} r={8} className="fill-accent" />
                      <text x={seg.anchor.x + 11} y={iconY - 5.5} textAnchor="middle" className="fill-accent-ink text-[10px] font-semibold">
                        {item.badge > 9 ? "9+" : item.badge}
                      </text>
                    </g>
                  ) : null}
                </g>
              );
            })}
          </g>
        );
      })}

      {/* Hub: the hovered or focused item, announced politely; the needle points at it. */}
      <g aria-hidden className="pointer-events-auto">
        <circle r={HUB_R - 2} className="fill-paper-2 stroke-field-edge" />
        {[0, 90, 180, 270].map((deg) => (
          <line key={deg} x1={0} y1={-(HUB_R - 2)} x2={0} y2={-(HUB_R - 8)} transform={`rotate(${deg})`} className="stroke-rule-2" strokeWidth={1.5} />
        ))}
        <g className="transition-transform duration-[var(--dur-short)] ease-[var(--ease-out)] motion-reduce:transition-none" style={{ transform: `rotate(${needle}deg)` }}>
          {/* A needle on the hub's rim, clear of the label in the middle. */}
          <path d={`M0 ${-(HUB_R - 3)} L5 ${-(HUB_R - 13)} L0 ${-(HUB_R - 19)} L-5 ${-(HUB_R - 13)} Z`} className="fill-accent" />
        </g>
      </g>
      <foreignObject x={-(HUB_R - 14)} y={-16} width={2 * (HUB_R - 14)} height={34} className="pointer-events-none">
        <div aria-live="polite" className="flex h-full flex-col items-center justify-center text-center leading-tight">
          <span className="sr-only">{shownItem ? position(shown, shownItem) : ""}</span>
          <span aria-hidden className="line-clamp-2 text-[11px] font-medium text-ink">
            {shownItem?.label}
          </span>
        </div>
      </foreignObject>
    </svg>
  );
}
