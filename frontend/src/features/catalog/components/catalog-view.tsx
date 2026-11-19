"use client";

import { Search, SearchX } from "lucide-react";
import { useTranslations } from "next-intl";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Checkbox, controlClasses } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { useCurrentUser } from "@/features/auth/hooks";
import { useCourses, useDomains } from "../hooks";
import { catalogQueryToSearch, DEFAULT_QUERY, hasActiveFilters, parseCatalogQuery } from "../query-state";
import { SORTS, type CatalogQuery, type Difficulty, type Sort } from "../types";
import { CourseCard } from "./course-card";

const SEARCH_DEBOUNCE_MS = 300;

export function CatalogView() {
  const t = useTranslations("catalog");
  const td = useTranslations("difficulty");
  const router = useRouter();
  const pathname = usePathname();
  const query = parseCatalogQuery(useSearchParams());
  const { data: user } = useCurrentUser();
  const domains = useDomains();
  const courses = useCourses(query);
  const searchId = useId();

  const [search, setSearch] = useState(query.q);

  function update(patch: Partial<CatalogQuery>) {
    // Any change except paging starts again from page 1.
    const next = { ...query, ...patch, page: patch.page ?? 1 };
    router.replace(`${pathname}${catalogQueryToSearch(next)}`, { scroll: false });
  }

  // Debounce typing into the URL.
  useEffect(() => {
    if (search.trim() === query.q.trim()) return;
    const timer = setTimeout(() => update({ q: search }), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only the typed text should restart the timer
  }, [search]);

  const page = courses.data;
  const select = "w-full sm:w-auto";

  return (
    <div className="flex flex-col gap-8">
      <form
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          update({ q: search });
        }}
        className="flex flex-col gap-4 border-b border-rule pb-6"
      >
        <div className="relative max-w-[36rem]">
          <label htmlFor={searchId} className="sr-only">
            {t("search")}
          </label>
          <Search aria-hidden className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted" />
          <input
            id={searchId}
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchPlaceholder")}
            maxLength={100}
            className={controlClasses(false, "h-11 pl-9")}
          />
        </div>
        <div className="flex flex-wrap items-end gap-3">
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink max-sm:w-full">
            {t("domain")}
            <select className={controlClasses(false, select)} value={query.domain} onChange={(e) => update({ domain: e.target.value })}>
              <option value="">{t("allDomains")}</option>
              {domains.data?.map((d) => (
                <option key={d.slug} value={d.slug}>
                  {d.name}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink max-sm:w-full">
            {t("difficulty")}
            <select
              className={controlClasses(false, select)}
              value={query.difficulty}
              onChange={(e) => update({ difficulty: e.target.value as Difficulty | "" })}
            >
              <option value="">{t("anyDifficulty")}</option>
              {(["beginner", "intermediate", "advanced"] as const).map((d) => (
                <option key={d} value={d}>
                  {td(d)}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink max-sm:w-full">
            {t("sort")}
            <select className={controlClasses(false, select)} value={query.sort} onChange={(e) => update({ sort: e.target.value as Sort })}>
              {SORTS.map((s) => (
                <option key={s} value={s}>
                  {t(`sorts.${s}`)}
                </option>
              ))}
            </select>
          </label>
          {user ? (
            <Checkbox
              label={t("favoritesOnly")}
              checked={query.favorited}
              onChange={(e) => update({ favorited: e.target.checked })}
              className="h-10"
            />
          ) : null}
        </div>
      </form>

      <div className="flex min-h-6 flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink" aria-live="polite">
          {page ? t("results", { count: page.meta.total }) : null}
        </p>
        {hasActiveFilters(query) ? (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSearch("");
              router.replace(`${pathname}${catalogQueryToSearch({ ...DEFAULT_QUERY, sort: query.sort })}`, { scroll: false });
            }}
          >
            {t("clearFilters")}
          </Button>
        ) : null}
      </div>

      {courses.isError ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("loadError")}</Alert>
          <Button variant="secondary" onClick={() => courses.refetch()}>
            {t("retry")}
          </Button>
        </div>
      ) : !page ? (
        <CatalogSkeleton />
      ) : page.data.length === 0 ? (
        <div className="panel flex flex-col items-start gap-2 p-8">
          <SearchX aria-hidden className="size-6 text-muted" />
          <h2 className="text-[length:var(--text-lg)]">{t("emptyTitle")}</h2>
          <p>{query.favorited && !query.q && !query.domain && !query.difficulty ? t("emptyFavorites") : t("emptyBody")}</p>
        </div>
      ) : (
        <ul
          className="panels grid-cols-1 sm:grid-cols-2 xl:grid-cols-3"
          aria-busy={courses.isFetching}
          style={{ opacity: courses.isPlaceholderData ? 0.6 : 1 }}
        >
          {page.data.map((course) => (
            <li key={course.slug}>
              <CourseCard course={course} />
            </li>
          ))}
        </ul>
      )}

      {page ? (
        <Pagination
          page={page.meta.current_page}
          totalPages={page.meta.last_page}
          onChange={(p) => {
            update({ page: p });
            window.scrollTo({ top: 0 });
          }}
          labels={{
            nav: t("pagination"),
            previous: t("previous"),
            next: t("next"),
            page: t("page", { page: page.meta.current_page, total: page.meta.last_page }),
          }}
        />
      ) : null}
    </div>
  );
}

function CatalogSkeleton() {
  return (
    <ul aria-hidden className="panels grid-cols-1 sm:grid-cols-2 xl:grid-cols-3">
      {Array.from({ length: 6 }, (_, i) => (
        <li key={i} className="flex flex-col">
          <div className="aspect-[5/2] bg-paper-3" />
          <div className="flex flex-col gap-3 p-5">
            <div className="h-5 w-2/3 rounded-chip bg-paper-3" />
            <div className="h-4 w-full rounded-chip bg-paper-3" />
            <div className="h-4 w-4/5 rounded-chip bg-paper-3" />
          </div>
        </li>
      ))}
    </ul>
  );
}
