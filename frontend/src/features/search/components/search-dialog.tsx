"use client";

import { keepPreviousData, useQuery } from "@tanstack/react-query";
import { BookOpen, Briefcase, GraduationCap, Search } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import { Alert } from "@/components/ui/alert";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { ariaKeys, isMacPlatform } from "@/lib/shortcuts/registry";
import { useBindings, useShortcut } from "@/lib/shortcuts/use-shortcut";
import { cn } from "@/lib/utils";
import { MIN_QUERY, search } from "../api";

const OPEN_SEARCH = "cybercompass:open-search";

/** Opens the header's search dialog from elsewhere (the compass), so there is one search. */
export const openSearch = () => document.dispatchEvent(new Event(OPEN_SEARCH));

/** Waits until typing pauses, so the API sees one request per pause rather than per key. */
function useDebounced<T>(value: T, ms: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const id = setTimeout(() => setDebounced(value), ms);
    return () => clearTimeout(id);
  }, [value, ms]);
  return debounced;
}

/**
 * Header search (D36): courses, lessons and careers, grouped. Opens from the button, its
 * shortcuts ("/" and Ctrl/⌘+K by default, D52) or the compass (`openSearch`). The native dialog traps focus, closes on Escape and returns focus to the button.
 */
export function SearchDialog() {
  const t = useTranslations("search");
  const [open, setOpen] = useState(false);
  const show = () => setOpen(true);
  useShortcut("search.open", show);
  useShortcut("search.openCombo", show);
  const keys = [...useBindings("search.open"), ...useBindings("search.openCombo")];
  useEffect(() => {
    const onOpen = () => setOpen(true);
    document.addEventListener(OPEN_SEARCH, onOpen);
    return () => document.removeEventListener(OPEN_SEARCH, onOpen);
  }, []);

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-label={t("open")}
        aria-keyshortcuts={ariaKeys(keys, isMacPlatform()) || undefined}
        title={t("shortcut")}
        className="flex size-9 items-center justify-center rounded-control text-ink-2 transition-[color,background-color] duration-[var(--dur-micro)] hover:bg-paper-3 hover:text-ink active:bg-paper-2"
      >
        <Search aria-hidden className="size-[18px]" />
      </button>
      <Dialog open={open} onClose={() => setOpen(false)} title={t("title")} closeLabel={t("close")} className="w-[min(40rem,calc(100vw-2*var(--gutter)))] self-start mt-[10vh]">
        <SearchPanel onPick={() => setOpen(false)} />
      </Dialog>
    </>
  );
}

/** `onPick` closes the dialog: after choosing a result, or on Escape. */
function SearchPanel({ onPick }: { onPick: () => void }) {
  const t = useTranslations("search");
  const inputId = useId();
  const inputRef = useRef<HTMLInputElement>(null);
  const [text, setText] = useState("");
  // The native dialog focuses its first control (the close button) before this panel mounts.
  useEffect(() => inputRef.current?.focus(), []);
  const term = useDebounced(text.trim(), 250);
  const ready = term.length >= MIN_QUERY;
  const results = useQuery({
    queryKey: ["search", term],
    queryFn: ({ signal }) => search(term, signal),
    enabled: ready,
    staleTime: 60_000,
    placeholderData: keepPreviousData,
  });
  const data = ready ? results.data : undefined;
  const total = data ? data.courses.length + data.lessons.length + data.careers.length : 0;

  return (
    <div className="flex flex-col gap-4">
      <div className="relative">
        <label htmlFor={inputId} className="sr-only">
          {t("label")}
        </label>
        <Search aria-hidden className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
        <input
          id={inputId}
          ref={inputRef}
          type="search"
          autoComplete="off"
          maxLength={100}
          value={text}
          onChange={(e) => setText(e.target.value)}
          onKeyDown={(e) => {
            // A search field's own Escape only clears it; here one Escape closes the search.
            if (e.key === "Escape") {
              e.preventDefault();
              onPick();
            }
          }}
          placeholder={t("placeholder")}
          className={controlClasses(false, "w-full pl-9")}
        />
      </div>
      <p aria-live="polite" className="text-sm text-muted">
        {!ready ? t("hint") : results.isError ? "" : data ? (total ? t("count", { count: total }) : t("none", { query: term })) : t("searching")}
      </p>
      {results.isError && ready ? <Alert tone="error">{t("error")}</Alert> : null}
      {data && total ? (
        <div className={cn("flex max-h-[55vh] flex-col gap-5 overflow-y-auto", results.isPlaceholderData && "opacity-60")}>
          <Group title={t("courses")} icon={<GraduationCap aria-hidden />} term={term} items={data.courses.map((c) => ({ key: c.slug, href: `/courses/${c.slug}`, title: c.title, meta: c.domain }))} onPick={onPick} />
          <Group title={t("lessons")} icon={<BookOpen aria-hidden />} term={term} items={data.lessons.map((l) => ({ key: l.slug, href: `/learn/${l.slug}`, title: l.title, meta: l.course }))} onPick={onPick} />
          <Group title={t("careers")} icon={<Briefcase aria-hidden />} term={term} items={data.careers.map((c) => ({ key: c.slug, href: `/careers/${c.slug}`, title: c.title, meta: c.summary }))} onPick={onPick} />
        </div>
      ) : null}
    </div>
  );
}

type Item = { key: string; href: string; title: string; meta: string | null };

function Group({ title, icon, items, term, onPick }: { title: string; icon: ReactNode; items: Item[]; term: string; onPick: () => void }) {
  const id = useId();
  if (!items.length) return null;
  return (
    <section aria-labelledby={id} className="flex flex-col gap-1.5">
      <h3 id={id} className="flex items-center gap-2 text-sm font-medium text-ink-2 [&_svg]:size-4 [&_svg]:text-muted">
        {icon}
        {title}
      </h3>
      <ul className="flex flex-col">
        {items.map((item) => (
          <li key={item.key}>
            <Link href={item.href} onClick={onPick} className="flex flex-col rounded-control px-2.5 py-2 hover:bg-paper-3 active:bg-paper">
              <span className="text-ink">
                <Highlight text={item.title} term={term} />
              </span>
              {item.meta ? <span className="truncate text-sm text-muted">{item.meta}</span> : null}
            </Link>
          </li>
        ))}
      </ul>
    </section>
  );
}

/** Marks where the term appears in a title, ignoring case. Plain text in, React elements out. */
export function Highlight({ text, term }: { text: string; term: string }) {
  const at = term ? text.toLowerCase().indexOf(term.toLowerCase()) : -1;
  if (at < 0) return <>{text}</>;
  return (
    <>
      {text.slice(0, at)}
      <mark className="rounded-[2px] bg-accent-soft text-ink">{text.slice(at, at + term.length)}</mark>
      {text.slice(at + term.length)}
    </>
  );
}
