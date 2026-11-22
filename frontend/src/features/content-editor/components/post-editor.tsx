"use client";

import { useId } from "react";
import { controlClasses } from "@/components/ui/input";
import { PostMeta, TagLinks } from "@/features/blog/components/post-meta";
import { errorAt } from "../editor-state";
import { DocumentEditor, Labelled, type DetailsProps, type EditorAdapter } from "./document-editor";
import { DocumentPreview } from "./lesson-preview";
import { StringListEditor } from "./list-editors";
import { MediaPicker } from "./media-picker";

/** The blog post editor (F2): the shared block-document editor (D55) with the post's own details. */
const postAdapter: EditorAdapter<"post"> = {
  kind: "post",
  messages: "blog.editor",
  historyMessages: "blog.history",
  listHref: "/admin/posts",
  liveUrl: (post) => (post.status === "published" ? `/blog/${post.slug}` : null),
  Details: PostDetails,
  Preview: ({ doc, dirty, t }) => (
    <DocumentPreview
      dirty={dirty}
      frameLabel={t("previewFrame")}
      title={doc.working_copy.title}
      summary={doc.working_copy.summary}
      sections={doc.preview_sections}
      before={
        <div className="flex flex-col gap-4">
          <PostMeta post={{ published_at: doc.published_at, estimated_minutes: doc.working_copy.estimated_minutes }} />
          <TagLinks tags={doc.working_copy.tags} />
          {doc.cover ? (
            // eslint-disable-next-line @next/next/no-img-element -- re-encoded WebP from the API's upload storage
            <img
              src={doc.cover.url}
              alt={doc.cover.alt}
              className="h-auto w-full rounded-control border border-rule bg-paper-3"
            />
          ) : null}
        </div>
      }
    />
  ),
  canUnarchive: true,
};

export function PostEditor({ id }: { id: number }) {
  return <DocumentEditor adapter={postAdapter} id={id} />;
}

/** Title, address, summary, cover and tags. The reading time is computed by the API on every save. */
function PostDetails({ copy, doc, errors, onChange, t }: DetailsProps<"post">) {
  const ids = { title: useId(), slug: useId(), summary: useId() };

  return (
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
          hint={doc.slug_editable ? t("slugHint") : t("slugLocked")}
        >
          <input
            id={ids.slug}
            value={copy.slug}
            maxLength={120}
            readOnly={!doc.slug_editable}
            onChange={(e) => onChange({ slug: e.target.value })}
            aria-invalid={Boolean(errorAt(errors, "slug"))}
            className={controlClasses(Boolean(errorAt(errors, "slug")), "measured text-sm")}
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
      <p className="text-sm">
        <span className="font-medium text-ink">{t("minutes")}: </span>
        <span className="measured text-ink">{t("readingTimeValue", { minutes: copy.estimated_minutes })}</span>{" "}
        <span className="text-muted">{t("readingTimeHint")}</span>
      </p>
      <div className="grid gap-6 lg:grid-cols-2">
        <MediaPicker
          label={t("cover")}
          hint={t("coverHint")}
          value={copy.cover ?? ""}
          error={errorAt(errors, "cover")}
          onChange={(cover) => onChange({ cover })}
          onClear={() => onChange({ cover: null })}
        />
        <StringListEditor
          label={t("tags")}
          items={copy.tags}
          onChange={(tags) => onChange({ tags })}
          errors={errors}
          errorPrefix="tags"
          maxItems={8}
          addLabel={t("addTag")}
        />
      </div>
      <p className="text-sm text-muted">{t("tagsHint")}</p>
    </section>
  );
}
