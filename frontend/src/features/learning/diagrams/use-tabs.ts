"use client";

import { useId, useRef, useState, type KeyboardEvent } from "react";

/**
 * WAI-ARIA tabs with automatic activation: arrow keys move and select, Home/End jump.
 * Returns props for the tablist, each tab and the panel.
 */
export function useTabs(count: number, { vertical = false, initial = 0 } = {}) {
  const [selected, setSelected] = useState(initial);
  const refs = useRef<(HTMLButtonElement | null)[]>([]);
  const id = useId();

  function onKeyDown(event: KeyboardEvent<HTMLButtonElement>) {
    const nextKey = vertical ? "ArrowDown" : "ArrowRight";
    const prevKey = vertical ? "ArrowUp" : "ArrowLeft";
    let next: number | null = null;
    if (event.key === nextKey) next = (selected + 1) % count;
    if (event.key === prevKey) next = (selected - 1 + count) % count;
    if (event.key === "Home") next = 0;
    if (event.key === "End") next = count - 1;
    if (next === null) return;
    event.preventDefault();
    setSelected(next);
    refs.current[next]?.focus();
  }

  return {
    selected,
    setSelected,
    listProps: { role: "tablist" as const, "aria-orientation": vertical ? ("vertical" as const) : ("horizontal" as const) },
    tabProps: (index: number) => ({
      ref: (el: HTMLButtonElement | null) => {
        refs.current[index] = el;
      },
      role: "tab" as const,
      id: `${id}-tab-${index}`,
      "aria-selected": selected === index,
      "aria-controls": `${id}-panel`,
      tabIndex: selected === index ? 0 : -1,
      onClick: () => setSelected(index),
      onKeyDown,
    }),
    panelProps: { role: "tabpanel" as const, id: `${id}-panel`, "aria-labelledby": `${id}-tab-${selected}`, tabIndex: 0 },
  };
}
