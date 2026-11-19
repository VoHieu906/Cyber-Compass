"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";
import { Button } from "./button";

type Props = {
  page: number;
  totalPages: number;
  onChange: (page: number) => void;
  labels: { nav: string; previous: string; next: string; page: string };
};

export function Pagination({ page, totalPages, onChange, labels }: Props) {
  if (totalPages <= 1) return null;
  return (
    <nav aria-label={labels.nav} className="flex items-center justify-between gap-4">
      <Button variant="secondary" size="sm" disabled={page <= 1} onClick={() => onChange(page - 1)}>
        <ChevronLeft aria-hidden />
        {labels.previous}
      </Button>
      <p className="measured text-sm text-ink" aria-live="polite">
        {labels.page}
      </p>
      <Button variant="secondary" size="sm" disabled={page >= totalPages} onClick={() => onChange(page + 1)}>
        {labels.next}
        <ChevronRight aria-hidden />
      </Button>
    </nav>
  );
}
