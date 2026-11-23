"use client";

import { useId } from "react";
import { controlClasses } from "@/components/ui/input";
import { errorAt } from "../editor-state";
import { useAdminCourses } from "../hooks";
import { ArchitecturePanel } from "./architecture-panel";
import { DocumentEditor, Labelled, type DetailsProps, type EditorAdapter } from "./document-editor";
import { LessonPreview } from "./lesson-preview";
import { ResourceListEditor, StringListEditor } from "./list-editors";

/** The lesson editor: the shared block-document editor (D55) with the lesson's details and exercise links. */
const lessonAdapter: EditorAdapter<"lesson"> = {
  kind: "lesson",
  messages: "content.editor",
  historyMessages: "content.history",
  listHref: "/admin/lessons",
  liveUrl: (lesson) => (lesson.status === "published" ? `/learn/${lesson.slug}` : null),
  subtitle: (lesson) => `${lesson.course.title} · ${lesson.chapter.title}`,
  Details: LessonDetails,
  Preview: ({ doc, dirty }) => <LessonPreview lesson={doc} dirty={dirty} />,
  references: (lesson) => lesson.exercise_references,
  publishNotice: (lesson) => (lesson.course.is_published ? null : "courseHidden"),
};

export function LessonEditor({ id }: { id: number }) {
  return <DocumentEditor adapter={lessonAdapter} id={id} />;
}

/** Metadata: where the lesson lives, its address, the reading frame around the blocks and its architecture (F3). */
function LessonDetails({ copy, doc: lesson, errors, onChange, t }: DetailsProps<"lesson">) {
  const courses = useAdminCourses();
  const ids = { title: useId(), slug: useId(), chapter: useId(), minutes: useId(), summary: useId() };

  return (
    <>
    <section aria-labelledby="details-heading" className="flex flex-col gap-5">
      <h2 id="details-heading" className="text-[length:var(--text-lg)]">
        {t("details")}
      </h2>
      <div className="grid gap-5 md:grid-cols-2">
        <Labelled id={ids.title} label={t("title")} error={errorAt(errors, "title")}>
          <input
            id={ids.title}
            value={copy.title}
            maxLength={160}
            onChange={(e) => onChange({ title: e.target.value })}
            aria-invalid={Boolean(errorAt(errors, "title"))}
            className={controlClasses(Boolean(errorAt(errors, "title")))}
          />
        </Labelled>
        <Labelled
          id={ids.slug}
          label={t("slug")}
          error={errorAt(errors, "slug")}
          hint={lesson.slug_editable ? t("slugHint") : t("slugLocked")}
        >
          <input
            id={ids.slug}
            value={copy.slug}
            maxLength={120}
            readOnly={!lesson.slug_editable}
            onChange={(e) => onChange({ slug: e.target.value })}
            aria-invalid={Boolean(errorAt(errors, "slug"))}
            className={controlClasses(Boolean(errorAt(errors, "slug")), "measured text-sm")}
          />
        </Labelled>
        <Labelled id={ids.chapter} label={t("chapter")} error={errorAt(errors, "chapter_id")} hint={t("chapterHint")}>
          <select
            id={ids.chapter}
            value={copy.chapter_id}
            onChange={(e) => onChange({ chapter_id: Number(e.target.value) })}
            className={controlClasses(Boolean(errorAt(errors, "chapter_id")))}
          >
            {courses.data ? null : <option value={copy.chapter_id}>{lesson.chapter.title}</option>}
            {courses.data?.map((course) => (
              <optgroup key={course.id} label={course.title}>
                {course.chapters.map((ch) => (
                  <option key={ch.id} value={ch.id}>
                    {ch.position}. {ch.title}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
        </Labelled>
        <Labelled id={ids.minutes} label={t("minutes")} error={errorAt(errors, "estimated_minutes")}>
          <input
            id={ids.minutes}
            type="number"
            min={1}
            max={240}
            value={copy.estimated_minutes}
            onChange={(e) => onChange({ estimated_minutes: Number(e.target.value) })}
            className={controlClasses(Boolean(errorAt(errors, "estimated_minutes")), "measured max-w-[8rem]")}
          />
        </Labelled>
      </div>
      <Labelled id={ids.summary} label={t("summary")} error={errorAt(errors, "summary")} hint={t("summaryHint")}>
        <textarea
          id={ids.summary}
          value={copy.summary}
          maxLength={300}
          rows={3}
          onChange={(e) => onChange({ summary: e.target.value })}
          aria-invalid={Boolean(errorAt(errors, "summary"))}
          className={controlClasses(Boolean(errorAt(errors, "summary")), "h-auto py-2")}
        />
      </Labelled>
      <div className="grid gap-6 lg:grid-cols-2">
        <StringListEditor
          label={t("objectives")}
          items={copy.objectives}
          onChange={(objectives) => onChange({ objectives })}
          errors={errors}
          errorPrefix="objectives"
          maxItems={10}
          addLabel={t("addObjective")}
        />
        <StringListEditor
          label={t("takeaways")}
          items={copy.key_takeaways}
          onChange={(key_takeaways) => onChange({ key_takeaways })}
          errors={errors}
          errorPrefix="key_takeaways"
          maxItems={10}
          addLabel={t("addTakeaway")}
        />
      </div>
      <ResourceListEditor items={copy.resources} onChange={(resources) => onChange({ resources })} errors={errors} />
    </section>
    <ArchitecturePanel copy={copy} lesson={lesson} errors={errors} onChange={onChange} />
    </>
  );
}
