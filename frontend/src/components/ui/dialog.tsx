"use client";

import { X } from "lucide-react";
import { useId, useLayoutEffect, useRef, type ReactNode } from "react";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  onClose: () => void;
  title: string;
  description?: ReactNode;
  closeLabel: string;
  children?: ReactNode;
  className?: string;
};

/**
 * A modal on the native <dialog> element: the browser provides the focus trap, Escape,
 * the inert background and returning focus to the opener. Opened with showModal().
 */
export function Dialog({ open, onClose, title, description, closeLabel, children, className }: Props) {
  const ref = useRef<HTMLDialogElement>(null);
  const titleId = useId();
  const descriptionId = useId();
  const opener = useRef<HTMLElement | null>(null);

  // A layout effect runs before the content's own effects, so the opener is recorded before
  // the content moves focus into the dialog.
  useLayoutEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) {
      opener.current = document.activeElement instanceof HTMLElement ? document.activeElement : null;
      dialog.showModal();
    }
    if (!open) {
      if (dialog.open) dialog.close();
      // When the content closes the dialog itself (a search pick, its own Escape), the content
      // unmounts before close() runs, focus falls to <body> and the browser no longer restores it.
      // Put it back where the dialog was opened from.
      const back = opener.current;
      opener.current = null;
      if (back?.isConnected && (document.activeElement === document.body || dialog.contains(document.activeElement)))
        back.focus();
    }
  }, [open]);

  return (
    <dialog
      ref={ref}
      aria-labelledby={titleId}
      aria-describedby={description ? descriptionId : undefined}
      onClose={onClose}
      // A click on the backdrop lands on the <dialog> itself, never on its content.
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
      className={cn(
        "m-auto w-[min(32rem,calc(100vw-2*var(--gutter)))] rounded-card border border-rule bg-paper-2 p-0 text-muted shadow-[var(--shadow-overlay)]",
        "backdrop:bg-[color-mix(in_oklab,var(--color-ink)_45%,transparent)]",
        className,
      )}
    >
      <div className="flex flex-col gap-5 p-6">
        <div className="flex items-start justify-between gap-4">
          <div className="flex flex-col gap-1.5">
            <h2 id={titleId} className="text-[length:var(--text-lg)]">
              {title}
            </h2>
            {description ? (
              <div id={descriptionId} className="text-sm">
                {description}
              </div>
            ) : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            aria-label={closeLabel}
            className="-m-1.5 rounded-control p-1.5 text-muted transition-[color] duration-[var(--dur-micro)] hover:text-ink"
          >
            <X aria-hidden className="size-4" />
          </button>
        </div>
        {open ? children : null}
      </div>
    </dialog>
  );
}
