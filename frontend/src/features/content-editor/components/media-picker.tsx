"use client";

import { ImagePlus, Images } from "lucide-react";
import { useTranslations } from "next-intl";
import { useId, useRef, useState } from "react";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { cn } from "@/lib/utils";
import { useMediaLibrary, useUploadMedia } from "../hooks";
import type { Media } from "../types";

const ACCEPT = "image/png,image/jpeg,image/webp";
const MAX_BYTES = 5 * 1024 * 1024;

/**
 * Chooses the image for a `media` block: upload a new one (alt text required) or pick an
 * earlier upload. The block stores only the image id; the API resolves the URL.
 */
export function MediaPicker({
  value,
  error,
  onChange,
}: {
  value: string;
  error?: string;
  onChange: (id: string) => void;
}) {
  const t = useTranslations("content.media");
  const tCommon = useTranslations("common");
  const [open, setOpen] = useState(false);
  const library = useMediaLibrary(open || Boolean(value));
  const selected = library.data?.data.find((m) => m.id === value);

  return (
    <div className="flex flex-col gap-2">
      <p className="text-sm font-medium text-ink">{t("image")}</p>
      {value && selected ? (
        <figure className="flex items-start gap-3">
          {/* eslint-disable-next-line @next/next/no-img-element -- re-encoded upload served from API storage */}
          <img
            src={selected.url}
            alt={selected.alt}
            className="h-20 w-32 shrink-0 rounded-control border border-rule bg-paper-3 object-cover"
          />
          <figcaption className="text-sm">
            <span className="text-muted">{t("altLabel")}: </span>
            {selected.alt}
          </figcaption>
        </figure>
      ) : value ? (
        <p className="measured text-xs text-muted">{value}</p>
      ) : (
        <p className="text-sm">{t("none")}</p>
      )}
      <div>
        <Button variant="secondary" size="sm" onClick={() => setOpen(true)}>
          <Images aria-hidden />
          {value ? t("change") : t("choose")}
        </Button>
      </div>
      {error ? <p className="text-sm text-bad">{error}</p> : null}

      <Dialog
        open={open}
        onClose={() => setOpen(false)}
        title={t("title")}
        closeLabel={tCommon("close")}
        className="w-[min(46rem,calc(100vw-2*var(--gutter)))]"
      >
        <Upload
          onUploaded={(media) => {
            onChange(media.id);
            setOpen(false);
          }}
        />
        <div className="flex flex-col gap-3 border-t border-rule pt-4">
          <p className="text-sm font-medium text-ink">{t("library")}</p>
          {library.isError ? <Alert tone="error">{t("loadError")}</Alert> : null}
          {library.data && library.data.data.length === 0 ? <p className="text-sm">{t("emptyLibrary")}</p> : null}
          <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3">
            {library.data?.data.map((m) => (
              <li key={m.id}>
                <button
                  type="button"
                  onClick={() => {
                    onChange(m.id);
                    setOpen(false);
                  }}
                  aria-pressed={m.id === value}
                  className={cn(
                    "flex w-full flex-col gap-1.5 rounded-control border p-1.5 text-left transition-[border-color] duration-[var(--dur-micro)] hover:border-muted",
                    m.id === value ? "border-accent" : "border-rule",
                  )}
                >
                  {/* eslint-disable-next-line @next/next/no-img-element -- re-encoded upload served from API storage */}
                  <img
                    src={m.url}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="aspect-[4/3] w-full rounded-[calc(var(--radius-control)-2px)] bg-paper-3 object-cover"
                  />
                  <span className="line-clamp-2 text-xs">{m.alt}</span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      </Dialog>
    </div>
  );
}

function Upload({ onUploaded }: { onUploaded: (media: Media) => void }) {
  const t = useTranslations("content.media");
  const upload = useUploadMedia();
  const errorMessage = useErrorMessage();
  const fileRef = useRef<HTMLInputElement>(null);
  const fileId = useId();
  const altId = useId();
  const [file, setFile] = useState<File | null>(null);
  const [alt, setAlt] = useState("");
  const [localError, setLocalError] = useState<string | null>(null);

  return (
    <form
      className="flex flex-col gap-3"
      onSubmit={(e) => {
        e.preventDefault();
        if (!file) return setLocalError(t("pickFile"));
        if (alt.trim().length < 3) return setLocalError(t("altRequired"));
        setLocalError(null);
        upload.mutate({ file, alt: alt.trim() }, { onSuccess: onUploaded });
      }}
    >
      <p className="text-sm font-medium text-ink">{t("upload")}</p>
      <div className="flex flex-col gap-1.5">
        <label htmlFor={fileId} className="text-sm">
          {t("file")}
        </label>
        <input
          ref={fileRef}
          id={fileId}
          type="file"
          accept={ACCEPT}
          onChange={(e) => {
            const picked = e.target.files?.[0] ?? null;
            if (picked && picked.size > MAX_BYTES) {
              setLocalError(t("tooLarge"));
              e.target.value = "";
              return setFile(null);
            }
            setLocalError(null);
            setFile(picked);
          }}
          className="text-sm text-muted file:mr-3 file:rounded-control file:border file:border-rule-2 file:bg-paper-2 file:px-3 file:py-1.5 file:text-sm file:text-ink"
        />
        <p className="text-xs text-muted">{t("fileHint")}</p>
      </div>
      <div className="flex flex-col gap-1.5">
        <label htmlFor={altId} className="text-sm">
          {t("alt")}
        </label>
        <input
          id={altId}
          value={alt}
          maxLength={250}
          onChange={(e) => setAlt(e.target.value)}
          className={controlClasses(false)}
        />
        <p className="text-xs text-muted">{t("altHint")}</p>
      </div>
      {localError ? <Alert tone="error">{localError}</Alert> : null}
      {upload.isError ? <Alert tone="error">{errorMessage(upload.error)}</Alert> : null}
      <div>
        <Button type="submit" size="sm" loading={upload.isPending}>
          <ImagePlus aria-hidden />
          {t("uploadAndUse")}
        </Button>
      </div>
    </form>
  );
}
