"use client";

import { FilePlus, Search, SearchX } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ApiError } from "@/lib/api/client";
import { useCreatePost, usePosts } from "../hooks";
import { slugify } from "../query-state";
import type { PostQuery } from "../types";
import { LessonStatusBadge, UnpublishedChip } from "./labels";

const SEARCH_DEBOUNCE_MS = 300;

/** Admin → Blog posts (F2): every post with its status, a search, a status filter and "New post". */
export function PostsView() {
  const t = useTranslations("blog.admin");
  const tStatus = useTranslations("content.status");
  const format = useFormatter();
  const searchId = useId();
  const [query, setQuery] = useState<PostQuery>({ q: "", status: "", page: 1 });
  const [search, setSearch] = useState("");
  const [creating, setCreating] = useState(false);
  const posts = usePosts(query);

  useEffect(() => {
    const timer = setTimeout(
      () => setQuery((q) => (q.q === search.trim() ? q : { ...q, q: search.trim(), page: 1 })),
      SEARCH_DEBOUNCE_MS,
    );
    return () => clearTimeout(timer);
  }, [search]);

  const page = posts.data;

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <h1 className="text-[length:var(--text-2xl)]">{t("title")}</h1>
        <Button onClick={() => setCreating(true)}>
          <FilePlus aria-hidden />
          {t("new")}
        </Button>
      </div>

      <form role="search" onSubmit={(e) => e.preventDefault()} className="flex flex-col gap-4 sm:flex-row sm:items-end">
        <div className="relative min-w-0 sm:flex-1">
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
        <label className="flex flex-col gap-1.5 text-sm font-medium text-ink">
          {t("status")}
          <select
            className={controlClasses(false)}
            value={query.status}
            onChange={(e) => setQuery({ ...query, status: e.target.value as PostQuery["status"], page: 1 })}
          >
            <option value="">{t("anyStatus")}</option>
            {(["published", "draft", "archived"] as const).map((s) => (
              <option key={s} value={s}>
                {tStatus(s)}
              </option>
            ))}
          </select>
        </label>
      </form>

      <p className="min-h-6 text-sm text-ink" aria-live="polite">
        {page ? t("results", { count: page.meta.total }) : null}
      </p>

      {posts.isError ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("loadError")}</Alert>
          <Button variant="secondary" onClick={() => posts.refetch()}>
            {t("retry")}
          </Button>
        </div>
      ) : !page ? (
        <div aria-hidden className="h-80 rounded-card bg-paper-3" />
      ) : page.data.length === 0 ? (
        <div className="panel flex flex-col items-start gap-2 p-8">
          <SearchX aria-hidden className="size-6 text-muted" />
          <p>{t("empty")}</p>
        </div>
      ) : (
        <div
          className="panel overflow-hidden"
          aria-busy={posts.isFetching}
          style={{ opacity: posts.isPlaceholderData ? 0.6 : 1 }}
        >
          <table className="w-full border-collapse text-left text-sm">
            <thead className="max-md:sr-only">
              <tr className="border-b border-rule text-xs text-muted">
                <th scope="col" className="px-5 py-3 font-medium">
                  {t("columns.post")}
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
              {page.data.map((p) => (
                <tr
                  key={p.id}
                  className="relative border-b border-rule transition-[background-color] duration-[var(--dur-micro)] last:border-b-0 hover:bg-paper-3 max-md:flex max-md:flex-col max-md:gap-2 max-md:p-4"
                >
                  <td className="px-5 py-3 max-md:p-0">
                    <Link
                      href={`/admin/posts/${p.id}`}
                      className="font-medium text-ink after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-focus"
                    >
                      {p.title}
                    </Link>
                    <p className="measured text-muted">{p.tags.map((tag) => `#${tag}`).join(" ") || p.slug}</p>
                  </td>
                  <td className="px-3 py-3 max-md:p-0">
                    <div className="flex flex-wrap items-center gap-2">
                      <LessonStatusBadge status={p.status} />
                      {p.has_unpublished_changes ? <UnpublishedChip /> : null}
                    </div>
                  </td>
                  <td className="measured px-3 py-3 text-right text-muted max-md:p-0 max-md:text-left max-md:text-xs">
                    {t("blockCount", { count: p.section_count })}
                  </td>
                  <td className="px-5 py-3 text-muted max-md:p-0 max-md:text-xs">
                    {p.last_edited_at ? (
                      <span className="measured">
                        {format.dateTime(new Date(p.last_edited_at), { dateStyle: "medium" })}
                      </span>
                    ) : null}
                    <span className="block">{p.last_edited_by ?? t("seeded")}</span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {page ? (
        <Pagination
          page={page.meta.current_page}
          totalPages={page.meta.last_page}
          onChange={(p) => {
            setQuery({ ...query, page: p });
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

      <NewPostDialog open={creating} onClose={() => setCreating(false)} />
    </div>
  );
}

/** Creates a draft post, then opens it in the editor. */
function NewPostDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const t = useTranslations("blog.admin.create");
  const tCommon = useTranslations("common");
  const router = useRouter();
  const create = useCreatePost();
  const errorMessage = useErrorMessage();
  const ids = { title: useId(), slug: useId() };
  const [title, setTitle] = useState("");
  const [slug, setSlug] = useState("");
  const [slugTouched, setSlugTouched] = useState(false);
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
          create.mutate(
            { title: title.trim(), slug: slug.trim() },
            { onSuccess: (post) => router.push(`/admin/posts/${post.id}`) },
          );
        }}
      >
        <div className="flex flex-col gap-1.5">
          <label htmlFor={ids.title} className="text-sm font-medium text-ink">
            {t("postTitle")}
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
          <Button type="submit" loading={create.isPending}>
            {t("create")}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}
