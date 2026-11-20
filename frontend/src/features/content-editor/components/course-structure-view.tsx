"use client";

import { Check, Pencil, Plus, X } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { moveItem } from "../editor-state";
import { useAdminCourses, useCreateChapter, useRenameChapter, useReorderChapters, useReorderLessons } from "../hooks";
import type { AdminCourse } from "../types";
import { LessonStatusBadge, UnpublishedChip } from "./labels";
import { RowControls } from "./list-editors";

/**
 * A course's chapters and lessons in learner order. Order and chapter names apply at once
 * (they are structure, not drafted content), and each change is in the audit log.
 */
export function CourseStructureView() {
  const t = useTranslations("content.courses");
  const router = useRouter();
  const pathname = usePathname();
  const params = useSearchParams();
  const courses = useAdminCourses();
  const selectId = useId();

  if (courses.isError) return <Alert tone="error">{t("loadError")}</Alert>;
  if (!courses.data) return <div aria-hidden className="h-96 rounded-card bg-paper-3" />;

  const wanted = Number(params.get("course"));
  const course = courses.data.find((c) => c.id === wanted) ?? courses.data[0];

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
      <p className="max-w-[44rem] text-sm">{t("intro")}</p>
      <div className="flex flex-col gap-1.5">
        <label htmlFor={selectId} className="text-sm font-medium text-ink">
          {t("course")}
        </label>
        <select
          id={selectId}
          className={controlClasses(false, "sm:max-w-[24rem]")}
          value={course?.id ?? ""}
          onChange={(e) => router.replace(`${pathname}?course=${e.target.value}`, { scroll: false })}
        >
          {courses.data.map((c) => (
            <option key={c.id} value={c.id}>
              {c.title}
            </option>
          ))}
        </select>
      </div>
      {course ? <CourseOutline key={course.id} course={course} /> : null}
    </div>
  );
}

function CourseOutline({ course }: { course: AdminCourse }) {
  const t = useTranslations("content.courses");
  const errorMessage = useErrorMessage();
  const reorderChapters = useReorderChapters();
  const reorderLessons = useReorderLessons();
  const createChapter = useCreateChapter();
  const [newTitle, setNewTitle] = useState("");
  const newId = useId();
  const failed = [reorderChapters, reorderLessons, createChapter].find((m) => m.isError);
  const pending = reorderChapters.isPending || reorderLessons.isPending;

  return (
    <div className="flex flex-col gap-5" aria-busy={pending}>
      {failed ? <Alert tone="error">{errorMessage(failed.error)}</Alert> : null}
      {!course.is_published ? <Alert tone="info">{t("courseHidden")}</Alert> : null}
      <ol className="flex flex-col gap-4">
        {course.chapters.map((chapter, ci) => (
          <li key={chapter.id} className="panel flex flex-col">
            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-rule px-4 py-3 sm:px-5">
              <ChapterTitle chapterId={chapter.id} position={chapter.position} title={chapter.title} />
              <RowControlsNoRemove
                index={ci}
                count={course.chapters.length}
                label={chapter.title}
                disabled={pending}
                onMove={(to) =>
                  reorderChapters.mutate({
                    courseId: course.id,
                    ids: moveItem(
                      course.chapters.map((c) => c.id),
                      ci,
                      to,
                    ),
                  })
                }
              />
            </div>
            {chapter.lessons.length === 0 ? (
              <p className="px-5 py-4 text-sm">{t("emptyChapter")}</p>
            ) : (
              <ol>
                {chapter.lessons.map((lesson, li) => (
                  <li
                    key={lesson.id}
                    className="flex flex-wrap items-center justify-between gap-3 border-b border-rule px-4 py-2.5 last:border-b-0 sm:px-5"
                  >
                    <div className="flex min-w-0 items-center gap-3">
                      <span className="measured w-5 shrink-0 text-right text-xs text-muted">{li + 1}</span>
                      <Link
                        href={`/admin/lessons/${lesson.id}`}
                        className="truncate text-sm font-medium text-ink underline-offset-4 hover:underline"
                      >
                        {lesson.title}
                      </Link>
                      <LessonStatusBadge status={lesson.status} />
                      {lesson.has_unpublished_changes ? <UnpublishedChip /> : null}
                    </div>
                    <RowControlsNoRemove
                      index={li}
                      count={chapter.lessons.length}
                      label={lesson.title}
                      disabled={pending}
                      onMove={(to) =>
                        reorderLessons.mutate({
                          chapterId: chapter.id,
                          ids: moveItem(
                            chapter.lessons.map((l) => l.id),
                            li,
                            to,
                          ),
                        })
                      }
                    />
                  </li>
                ))}
              </ol>
            )}
          </li>
        ))}
      </ol>
      <form
        className="flex flex-col gap-2 sm:flex-row sm:items-end"
        onSubmit={(e) => {
          e.preventDefault();
          if (!newTitle.trim()) return;
          createChapter.mutate({ courseId: course.id, title: newTitle.trim() }, { onSuccess: () => setNewTitle("") });
        }}
      >
        <div className="flex flex-1 flex-col gap-1.5">
          <label htmlFor={newId} className="text-sm font-medium text-ink">
            {t("newChapter")}
          </label>
          <input
            id={newId}
            value={newTitle}
            maxLength={160}
            onChange={(e) => setNewTitle(e.target.value)}
            className={controlClasses(false)}
          />
        </div>
        <Button type="submit" variant="secondary" loading={createChapter.isPending} disabled={!newTitle.trim()}>
          <Plus aria-hidden />
          {t("addChapter")}
        </Button>
      </form>
      <p className="text-sm text-muted">{t("reorderNote")}</p>
    </div>
  );
}

/** Move up/down only: chapters and lessons are never deleted from here. Disabled while a move is saving. */
function RowControlsNoRemove({
  index,
  count,
  label,
  disabled,
  onMove,
}: {
  index: number;
  count: number;
  label: string;
  disabled: boolean;
  onMove: (to: number) => void;
}) {
  return (
    <fieldset disabled={disabled}>
      <RowControls index={index} count={count} label={label} onMove={onMove} />
    </fieldset>
  );
}

function ChapterTitle({ chapterId, position, title }: { chapterId: number; position: number; title: string }) {
  const t = useTranslations("content.courses");
  const rename = useRenameChapter();
  const errorMessage = useErrorMessage();
  const [editing, setEditing] = useState(false);
  const [value, setValue] = useState(title);
  const inputId = useId();

  if (!editing) {
    return (
      <div className="flex min-w-0 items-center gap-2">
        <h2 className="truncate text-base font-medium text-ink">
          <span className="measured mr-2 text-sm text-muted">{position}.</span>
          {title}
        </h2>
        <button
          type="button"
          onClick={() => {
            setValue(title);
            setEditing(true);
          }}
          aria-label={t("rename", { title })}
          className="inline-flex size-8 items-center justify-center rounded-control text-muted hover:bg-paper-3 hover:text-ink active:bg-paper-2 disabled:opacity-50 [&_svg]:size-4"
        >
          <Pencil aria-hidden />
        </button>
      </div>
    );
  }

  return (
    <form
      className="flex min-w-0 flex-1 flex-col gap-1"
      onSubmit={(e) => {
        e.preventDefault();
        rename.mutate({ chapterId, title: value.trim() }, { onSuccess: () => setEditing(false) });
      }}
    >
      <div className="flex items-center gap-2">
        <label htmlFor={inputId} className="sr-only">
          {t("chapterTitle")}
        </label>
        <input
          id={inputId}
          value={value}
          maxLength={160}
          autoFocus
          onChange={(e) => setValue(e.target.value)}
          className={controlClasses(rename.isError, "h-9")}
        />
        <Button
          type="submit"
          size="icon"
          variant="secondary"
          aria-label={t("saveTitle")}
          loading={rename.isPending}
          disabled={!value.trim()}
          className="size-9"
        >
          <Check aria-hidden />
        </Button>
        <Button
          size="icon"
          variant="ghost"
          aria-label={t("cancelRename")}
          onClick={() => setEditing(false)}
          className="size-9"
        >
          <X aria-hidden />
        </Button>
      </div>
      {rename.isError ? <p className="text-sm text-bad">{errorMessage(rename.error)}</p> : null}
    </form>
  );
}
