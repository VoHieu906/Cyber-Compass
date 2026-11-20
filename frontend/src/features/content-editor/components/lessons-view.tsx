"use client";

import { FilePlus, Search, SearchX } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { Checkbox, controlClasses } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ApiError } from "@/lib/api/client";
import { useAdminCourses, useCreateLesson, useLessons } from "../hooks";
import { DEFAULT_LESSON_QUERY, lessonQueryToSearch, parseLessonQuery, slugify } from "../query-state";
import { LESSON_SORTS, type AdminLessonListItem, type LessonQuery } from "../types";
import { LessonStatusBadge, UnpublishedChip } from "./labels";

const SEARCH_DEBOUNCE_MS = 300;

export function LessonsView() {
  const t = useTranslations("content.lessons");
  const tStatus = useTranslations("content.status");
  const router = useRouter();
  const pathname = usePathname();
  const query = parseLessonQuery(useSearchParams());
  const lessons = useLessons(query);
  const courses = useAdminCourses();
  const searchId = useId();
  const [search, setSearch] = useState(query.q);
  const [creating, setCreating] = useState(false);

  function update(patch: Partial<LessonQuery>) {
    const next = { ...query, ...patch, page: patch.page ?? 1 };
    router.replace(`${pathname}${lessonQueryToSearch(next)}`, { scroll: false });
  }

  useEffect(() => {
    if (search.trim() === query.q.trim()) return;
    const timer = setTimeout(() => update({ q: search }), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only the typed text should restart the timer
  }, [search]);

  const page = lessons.data;
  const filtered = Boolean(query.q.trim() || query.course || query.status || query.drafts);

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
        <Button onClick={() => setCreating(true)}>
          <FilePlus aria-hidden />
          {t("new")}
        </Button>
      </div>

      <form
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          update({ q: search });
        }}
        className="flex flex-col gap-4 lg:flex-row lg:flex-wrap lg:items-end"
      >
        <div className="relative min-w-0 lg:min-w-[16rem] lg:flex-1">
          <label htmlFor={searchId} className="sr-only">
            {t("search")}
          </label>
          <Search
            aria-hidden
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
          />
          <input
            id={searchId}
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchPlaceholder")}
            maxLength={100}
            className={controlClasses(false, "pl-9")}
          />
        </div>
        <div className="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end">
          <label className="col-span-2 flex flex-col gap-1.5 text-sm font-medium text-ink sm:col-span-1">
            {t("course")}
            <select
              className={controlClasses(false, "w-full sm:w-auto sm:max-w-[16rem]")}
              value={query.course ?? ""}
              onChange={(e) => update({ course: e.target.value ? Number(e.target.value) : null })}
            >
              <option value="">{t("anyCourse")}</option>
              {courses.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.title}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("status")}
            <select
              className={controlClasses(false)}
              value={query.status}
              onChange={(e) => update({ status: e.target.value as LessonQuery["status"] })}
            >
              <option value="">{t("anyStatus")}</option>
              {(["published", "draft", "archived"] as const).map((s) => (
                <option key={s} value={s}>
                  {tStatus(s)}
                </option>
              ))}
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("sort")}
            <select
              className={controlClasses(false)}
              value={query.sort}
              onChange={(e) => update({ sort: e.target.value as LessonQuery["sort"] })}
            >
              {LESSON_SORTS.map((s) => (
                <option key={s} value={s}>
                  {t(`sorts.${s}`)}
                </option>
              ))}
            </select>
          </label>
          <Checkbox
            label={t("onlyDrafts")}
            checked={query.drafts}
            onChange={(e) => update({ drafts: e.target.checked })}
            className="col-span-2 h-10"
          />
        </div>
      </form>

      <div className="flex min-h-6 flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink" aria-live="polite">
          {page ? t("results", { count: page.meta.total }) : null}
        </p>
        {filtered ? (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSearch("");
              router.replace(`${pathname}${lessonQueryToSearch({ ...DEFAULT_LESSON_QUERY, sort: query.sort })}`, {
                scroll: false,
              });
            }}
          >
            {t("clearFilters")}
          </Button>
        ) : null}
      </div>

      {lessons.isError ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("loadError")}</Alert>
          <Button variant="secondary" onClick={() => lessons.refetch()}>
            {t("retry")}
          </Button>
        </div>
      ) : !page ? (
        <div aria-hidden className="h-80 rounded-card bg-paper-3" />
      ) : page.data.length === 0 ? (
        <div className="panel flex flex-col items-start gap-2 p-8">
          <SearchX aria-hidden className="size-6 text-muted" />
          <h2 className="text-[length:var(--text-lg)]">{t("emptyTitle")}</h2>
          <p>{t("emptyBody")}</p>
        </div>
      ) : (
        <LessonTable lessons={page.data} busy={lessons.isFetching} dimmed={lessons.isPlaceholderData} />
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

      <NewLessonDialog open={creating} onClose={() => setCreating(false)} defaultCourse={query.course} />
    </div>
  );
}

function LessonTable({ lessons, busy, dimmed }: { lessons: AdminLessonListItem[]; busy: boolean; dimmed: boolean }) {
  const t = useTranslations("content.lessons");
  const format = useFormatter();

  return (
    <div className="panel overflow-hidden" aria-busy={busy} style={{ opacity: dimmed ? 0.6 : 1 }}>
      <table className="w-full border-collapse text-left text-sm">
        <thead className="max-md:sr-only">
          <tr className="border-b border-rule text-xs text-muted">
            <th scope="col" className="px-5 py-3 font-medium">
              {t("columns.lesson")}
            </th>
            <th scope="col" className="px-3 py-3 font-medium">
              {t("columns.status")}
            </th>
            <th scope="col" className="px-3 py-3 text-right font-medium">
              {t("columns.blocks")}
            </th>
            <th scope="col" className="px-5 py-3 font-medium">
              {t("columns.edited")}
            </th>
          </tr>
        </thead>
        <tbody>
          {lessons.map((l) => (
            <tr
              key={l.id}
              className="relative border-b border-rule transition-[background-color] duration-[var(--dur-micro)] last:border-b-0 hover:bg-paper-3 max-md:flex max-md:flex-col max-md:gap-2 max-md:p-4"
            >
              <td className="px-5 py-3 max-md:p-0">
                <Link
                  href={`/admin/lessons/${l.id}`}
                  className="font-medium text-ink after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-focus"
                >
                  {l.title}
                </Link>
                <p className="text-muted">
                  {l.course.title} · {l.chapter.title}
                </p>
              </td>
              <td className="px-3 py-3 max-md:p-0">
                <div className="flex flex-wrap items-center gap-2">
                  <LessonStatusBadge status={l.status} />
                  {l.has_unpublished_changes ? <UnpublishedChip /> : null}
                </div>
              </td>
              <td className="measured px-3 py-3 text-right text-muted max-md:p-0 max-md:text-left max-md:text-xs">
                {t("blockCounts", { blocks: l.section_count, visuals: l.visual_count })}
              </td>
              <td className="px-5 py-3 text-muted max-md:p-0 max-md:text-xs">
                {l.last_edited_at ? (
                  <span className="measured">
                    {format.dateTime(new Date(l.last_edited_at), { dateStyle: "medium" })}
                  </span>
                ) : null}
                {l.last_edited_by ? (
                  <span className="block">{l.last_edited_by}</span>
                ) : (
                  <span className="block">{t("seeded")}</span>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/** Creates a draft lesson at the end of a chapter, then opens it in the editor. */
function NewLessonDialog({
  open,
  onClose,
  defaultCourse,
}: {
  open: boolean;
  onClose: () => void;
  defaultCourse: number | null;
}) {
  const t = useTranslations("content.lessons.create");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const courses = useAdminCourses();
  const create = useCreateLesson();
  const errorMessage = useErrorMessage();
  const ids = { course: useId(), chapter: useId(), title: useId(), slug: useId() };
  const [courseId, setCourseId] = useState<number | null>(defaultCourse);
  const [chapterId, setChapterId] = useState<number | null>(null);
  const [title, setTitle] = useState("");
  const [slug, setSlug] = useState("");
  const [slugTouched, setSlugTouched] = useState(false);

  const course = courses.data?.find((c) => c.id === (courseId ?? courses.data?.[0]?.id));
  const chapter = course?.chapters.find((c) => c.id === chapterId) ?? course?.chapters[0];
  const fieldErrors = create.error instanceof ApiError ? create.error.fieldErrors : {};

  function close() {
    create.reset();
    setTitle("");
    setSlug("");
    setSlugTouched(false);
    onClose();
  }

  return (
    <Dialog open={open} onClose={close} title={t("title")} description={t("body")} closeLabel={tCommon("close")}>
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          if (!chapter) return;
          create.mutate(
            { chapter_id: chapter.id, title: title.trim(), slug: slug.trim() },
            { onSuccess: (lesson) => router.push(`/admin/lessons/${lesson.id}`) },
          );
        }}
      >
        <div className="grid gap-4 sm:grid-cols-2">
          <div className="flex flex-col gap-1.5">
            <label htmlFor={ids.course} className="text-sm font-medium text-ink">
              {t("course")}
            </label>
            <select
              id={ids.course}
              className={controlClasses(false)}
              value={course?.id ?? ""}
              onChange={(e) => {
                setCourseId(Number(e.target.value));
                setChapterId(null);
              }}
            >
              {courses.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.title}
                </option>
              ))}
            </select>
          </div>
          <div className="flex flex-col gap-1.5">
            <label htmlFor={ids.chapter} className="text-sm font-medium text-ink">
              {t("chapter")}
            </label>
            <select
              id={ids.chapter}
              className={controlClasses(Boolean(fieldErrors.chapter_id))}
              value={chapter?.id ?? ""}
              onChange={(e) => setChapterId(Number(e.target.value))}
            >
              {course?.chapters.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.position}. {c.title}
                </option>
              ))}
            </select>
          </div>
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={ids.title} className="text-sm font-medium text-ink">
            {t("lessonTitle")}
          </label>
          <input
            id={ids.title}
            value={title}
            maxLength={160}
            required
            onChange={(e) => {
              setTitle(e.target.value);
              if (!slugTouched) setSlug(slugify(e.target.value));
            }}
            className={controlClasses(Boolean(fieldErrors.title))}
          />
          {fieldErrors.title ? <p className="text-sm text-bad">{fieldErrors.title[0]}</p> : null}
        </div>
        <div className="flex flex-col gap-1.5">
          <label htmlFor={ids.slug} className="text-sm font-medium text-ink">
            {t("slug")}
          </label>
          <input
            id={ids.slug}
            value={slug}
            maxLength={120}
            required
            onChange={(e) => {
              setSlugTouched(true);
              setSlug(e.target.value);
            }}
            className={controlClasses(Boolean(fieldErrors.slug), "measured text-sm")}
          />
          <p className={fieldErrors.slug ? "text-sm text-bad" : "text-sm text-muted"}>
            {fieldErrors.slug?.[0] ?? t("slugHint", { slug: slug || "…" })}
          </p>
        </div>
        {create.isError && !(create.error instanceof ApiError && create.error.isValidation) ? (
          <Alert tone="error">{errorMessage(create.error)}</Alert>
        ) : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={close}>
            {tCommon("cancel")}
          </Button>
          <Button type="submit" loading={create.isPending} disabled={!chapter}>
            {t("create")}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
