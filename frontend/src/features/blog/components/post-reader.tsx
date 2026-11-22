"use client";

import { ArrowLeft } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { DocumentLayout } from "@/features/learning/components/document-layout";
import type { Post } from "../types";
import { PostMeta, TagLinks } from "./post-meta";
import { ReadingProgress } from "./reading-progress";

const ARTICLE_ID = "post-body";

/**
 * A blog post in the lesson reader's layout (D55): the same blocks, "On this page" list and compass
 * items, plus the long-read additions (reading progress, heading links, heavy blocks mounted near
 * the viewport). Reading a post records nothing: posts are not part of learning progress.
 */
export function PostReader({ post }: { post: Post }) {
  const t = useTranslations();

  return (
    <div id={ARTICLE_ID}>
      <ReadingProgress targetId={ARTICLE_ID} />
      <DocumentLayout
        enhanced
        sections={post.sections}
        header={
          <header className="flex flex-col gap-5">
            <Breadcrumb
              label={t("common.breadcrumb")}
              items={[{ href: "/blog", label: t("blog.title") }, { label: post.title }]}
            />
            <PostMeta post={post} />
            <h1 className="text-[length:var(--text-display-s)]">{post.title}</h1>
            <p className="text-[length:var(--text-lead)]">{post.summary}</p>
            {post.author ? <p className="text-sm">{t("blog.byline", { name: post.author })}</p> : null}
            <TagLinks tags={post.tags} />
            {post.cover ? (
              // eslint-disable-next-line @next/next/no-img-element -- re-encoded WebP from the API's upload storage
              <img
                src={post.cover.url}
                alt={post.cover.alt}
                width={post.cover.width ?? undefined}
                height={post.cover.height ?? undefined}
                className="h-auto w-full rounded-control border border-rule bg-paper-3"
              />
            ) : null}
          </header>
        }
        footer={
          <footer className="flex flex-col gap-4 border-t border-rule pt-8">
            <TagLinks tags={post.tags} />
            <Link
              href="/blog"
              className="inline-flex items-center gap-1.5 self-start text-ink underline decoration-rule-2 underline-offset-4 hover:decoration-accent"
            >
              <ArrowLeft aria-hidden className="size-4" />
              {t("blog.backToBlog")}
            </Link>
          </footer>
        }
      />
    </div>
  );
}
