"use client";

import { useTranslations } from "next-intl";
import { useCallback } from "react";

/** Message lookup: a document type's own wording first, then the shared wording (D55). */
export type DocText = (key: string, values?: Record<string, string | number>) => string;

export function useDocText(namespace: string, fallback = "content.editor"): DocText {
  const shared = useTranslations(fallback);
  const own = useTranslations(namespace);
  return useCallback(
    (key: string, values?: Record<string, string | number>) =>
      namespace !== fallback && own.has(key) ? own(key, values) : shared(key, values),
    [namespace, fallback, own, shared],
  );
}
