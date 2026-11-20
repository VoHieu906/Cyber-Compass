"use client";

import { Box } from "lucide-react";
import { useTranslations } from "next-intl";
import { useTheme } from "next-themes";
import { useEffect, useId, useRef, useState } from "react";
import { cn } from "@/lib/utils";
import {
  isFrameMessage,
  type BlockKind,
  type FrameStatus,
  type FrameStrings,
  type ParentMessage,
} from "@/sandbox/scene3d/protocol";
import { aspectRatio, type Aspect } from "@/sandbox/scene3d/schema";

const FRAME_SRC = "/sandbox/scene3d/frame.html";
const STEPS_PANEL_PX = 113;

export type SceneStatus = { status: FrameStatus; message?: string; line?: number };

type Props = {
  kind: BlockKind;
  payload: Record<string, unknown>;
  /** Editor previews: errors are shown with their message and line, and every change re-renders. */
  debug?: boolean;
  onStatus?: (status: SceneStatus) => void;
  className?: string;
};

/**
 * A 3D block (scene-3d or script-3d), drawn inside the sandbox described in
 * docs/SANDBOXED_3D.md. This component never runs scene code: it hands the payload to an
 * <iframe sandbox="allow-scripts"> (opaque origin, no network) and only accepts typed status
 * messages from that exact frame. The text description is always available, and replaces
 * the 3D view when it can't run.
 */
export function SceneFrame({ kind, payload, debug = false, onStatus, className }: Props) {
  const t = useTranslations("learn.scene");
  const { resolvedTheme } = useTheme();
  const theme = resolvedTheme === "dark" ? "dark" : "light";
  const wrapperRef = useRef<HTMLDivElement>(null);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const descriptionId = useId();
  const [near, setNear] = useState(false);
  const [ready, setReady] = useState(false);
  const [status, setStatus] = useState<SceneStatus>({ status: "loading" });

  const title = typeof payload.title === "string" && payload.title ? payload.title : t("untitled");
  const description = typeof payload.description === "string" ? payload.description : "";
  const caption = typeof payload.caption === "string" ? payload.caption : null;
  const disabled = payload.disabled === true;
  const ratio = aspectRatio((payload.aspect as Aspect | undefined) ?? "16:9");
  const hasSteps = kind === "scene-3d" && Array.isArray(payload.steps) && payload.steps.length > 0;
  const failed = status.status === "error" || status.status === "stalled" || status.status === "unsupported";

  // Mount the sandbox only when the block is about to scroll into view, then pause it whenever it leaves.
  useEffect(() => {
    const node = wrapperRef.current;
    if (!node || disabled) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) setNear(true);
        post({ type: "visibility", visible: entry.isIntersecting });
      },
      { rootMargin: "200px" },
    );
    observer.observe(node);
    return () => observer.disconnect();
  }, [disabled]);

  function post(message: ParentMessage) {
    // The frame's origin is opaque ("null") and can't be named as a target; nothing secret is sent.
    iframeRef.current?.contentWindow?.postMessage(message, "*");
  }

  useEffect(() => {
    function onMessage(event: MessageEvent) {
      if (!iframeRef.current || event.source !== iframeRef.current.contentWindow || !isFrameMessage(event.data)) return;
      if (event.data.type === "ready") return setReady(true);
      const next: SceneStatus = {
        status: event.data.status,
        message: typeof event.data.message === "string" ? event.data.message.slice(0, 300) : undefined,
        line: typeof event.data.line === "number" ? event.data.line : undefined,
      };
      setStatus(next);
      onStatus?.(next);
    }
    window.addEventListener("message", onMessage);
    return () => window.removeEventListener("message", onMessage);
  }, [onStatus]);

  const strings: FrameStrings = {
    play: t("play"),
    pause: t("pause"),
    resetView: t("resetView"),
    previousStep: t("previousStep"),
    nextStep: t("nextStep"),
    stepOf: t("stepOf", { current: "{current}", total: "{total}" }),
    close: t("close"),
    stalled: t("stalled"),
    failed: t("failed"),
    unsupported: t("unsupported"),
    hint: t("hint"),
  };
  const serialized = JSON.stringify({ payload, theme, strings });

  useEffect(() => {
    if (!ready) return;
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const { payload: data, strings: text } = JSON.parse(serialized) as { payload: unknown; strings: FrameStrings };
    post({ type: "render", kind, payload: data, theme, reducedMotion, strings: text, debug });
  }, [ready, serialized, kind, theme, debug]);

  return (
    <figure className={cn("flex flex-col gap-3", className)}>
      {disabled || (failed && !debug) ? null : (
        <div
          ref={wrapperRef}
          className="[container-type:inline-size] overflow-hidden rounded-control border border-rule bg-paper-3"
        >
          {near ? (
            <iframe
              ref={iframeRef}
              src={FRAME_SRC}
              title={title}
              aria-describedby={description ? descriptionId : undefined}
              // Scripts only: no same-origin, forms, popups, top navigation or downloads (docs/SANDBOXED_3D.md §4).
              sandbox="allow-scripts"
              referrerPolicy="no-referrer"
              loading="lazy"
              className="block w-full border-0"
              style={{ height: `calc(100cqw / ${ratio} + ${hasSteps ? STEPS_PANEL_PX : 0}px)` }}
            />
          ) : (
            <div
              aria-hidden
              className="flex items-center justify-center text-muted"
              style={{ height: `calc(100cqw / ${ratio} + ${hasSteps ? STEPS_PANEL_PX : 0}px)` }}
            >
              <Box className="size-6 opacity-60" />
            </div>
          )}
        </div>
      )}
      {caption ? <figcaption className="text-sm">{caption}</figcaption> : null}
      {description ? (
        <details
          open={disabled || (failed && !debug)}
          className="group rounded-control border border-rule px-4 py-2.5 text-sm"
        >
          <summary className="cursor-pointer rounded-chip font-medium text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus">
            {disabled || (failed && !debug) ? t("descriptionInstead") : t("description")}
          </summary>
          <p id={descriptionId} className="mt-2 whitespace-pre-line">
            {description}
          </p>
        </details>
      ) : null}
    </figure>
  );
}
