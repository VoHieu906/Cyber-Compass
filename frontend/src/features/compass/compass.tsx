"use client";

import { Compass as CompassIcon } from "lucide-react";
import { useTranslations } from "next-intl";
import { lazy, Suspense, useCallback, useEffect, useId, useRef, useState } from "react";
import { useNavPrefs } from "@/features/shortcuts/prefs";
import { ariaKeys, isMacPlatform, keyParts } from "@/lib/shortcuts/registry";
import { useBindings, useShortcut } from "@/lib/shortcuts/use-shortcut";

const loadMenu = () => import("./compass-menu");
// The rings, items and their icons load on first hover, focus or shortcut, not with the page.
const CompassMenu = lazy(loadMenu);

/** The compass (D51): mounted only while the learner has it switched on (Preferences). */
export function Compass() {
  const { ready, compassEnabled } = useNavPrefs();
  return ready && compassEnabled ? <CompassTrigger /> : null;
}

function CompassTrigger() {
  const t = useTranslations("compass");
  const [open, setOpen] = useState(false);
  const menuId = useId();
  const button = useRef<HTMLButtonElement>(null);
  const intent = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
  const hoverOpenedAt = useRef(0);
  const keys = useBindings("compass.toggle");

  const close = useCallback(() => {
    setOpen(false);
    button.current?.focus({ preventScroll: true });
  }, []);

  useShortcut("compass.toggle", () => (open ? close() : setOpen(true)));
  useEffect(() => () => clearTimeout(intent.current), []);

  const hint = keys[0] ? keyParts(keys[0], isMacPlatform()).join(isMacPlatform() ? "" : "+") : null;

  return (
    <>
      <button
        ref={button}
        type="button"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        aria-label={t("open")}
        aria-keyshortcuts={ariaKeys(keys, isMacPlatform()) || undefined}
        title={hint ? `${t("open")} (${hint})` : t("open")}
        onFocus={() => void loadMenu()}
        onPointerEnter={(e) => {
          void loadMenu();
          if (e.pointerType !== "mouse" || open) return;
          intent.current = setTimeout(() => {
            hoverOpenedAt.current = Date.now();
            setOpen(true);
          }, 120);
        }}
        onPointerLeave={() => clearTimeout(intent.current)}
        onClick={() => {
          clearTimeout(intent.current);
          // A click right after hover opened the menu means "open", not "close".
          if (open && Date.now() - hoverOpenedAt.current < 800) return;
          if (open) close();
          else setOpen(true);
        }}
        className="compass-trigger fixed z-[var(--z-sticky)] flex size-11 items-center justify-center rounded-full border border-field-edge bg-paper-2 text-ink shadow-[var(--shadow-overlay)] transition-[color,background-color,transform] duration-[var(--dur-micro)] hover:bg-paper-3 aria-expanded:text-accent print:hidden"
      >
        <CompassIcon aria-hidden className="size-5" />
      </button>
      {open ? (
        <Suspense fallback={null}>
          <CompassMenu id={menuId} triggerRef={button} onClose={close} />
        </Suspense>
      ) : null}
    </>
  );
}
