import { ExternalLink } from "lucide-react";
import type { ReactNode } from "react";
import { isHttpsUrl } from "../blocks/schemas";

/**
 * Renders the lesson prose format: blank-line separated paragraphs, "- " bullet lists,
 * "1. " numbered lists, `inline code`, **bold** and [links](https://…). Everything becomes
 * React text nodes, never HTML, so content cannot inject markup or script. Only https links
 * become links (the API refuses others on save); anything else stays plain text.
 */

type Block =
  | { kind: "paragraph"; text: string }
  | { kind: "list"; items: string[] }
  | { kind: "ordered"; items: string[]; start: number };

const NUMBERED = /^(\d{1,3})[.)]\s+/;

export function parseBlocks(text: string): Block[] {
  return text
    .replace(/\r\n/g, "\n")
    .split(/\n{2,}/)
    .map((chunk) => chunk.trim())
    .filter(Boolean)
    .map((chunk): Block => {
      const lines = chunk.split("\n").map((l) => l.trim());
      if (lines.every((l) => l.startsWith("- "))) {
        return { kind: "list", items: lines.map((l) => l.slice(2).trim()) };
      }
      if (lines.every((l) => NUMBERED.test(l))) {
        return {
          kind: "ordered",
          start: Number(NUMBERED.exec(lines[0])![1]),
          items: lines.map((l) => l.replace(NUMBERED, "").trim()),
        };
      }
      return { kind: "paragraph", text: lines.join(" ") };
    });
}

const TOKENS = /(`[^`]+`|\*\*[^*]+\*\*|\[[^\]\n]+\]\([^)\s]*\))/g;
const LINK = /^\[([^\]\n]+)\]\(([^)\s]*)\)$/;

export function renderInline(text: string): ReactNode[] {
  return text
    .split(TOKENS)
    .filter((part) => part !== "")
    .map((part, i) => {
      if (part.startsWith("`") && part.endsWith("`") && part.length > 2) {
        return (
          <code
            key={i}
            className="measured rounded-chip border border-rule bg-paper-3 px-1 py-px text-[0.9em] text-ink"
          >
            {part.slice(1, -1)}
          </code>
        );
      }
      if (part.startsWith("**") && part.endsWith("**") && part.length > 4) {
        return (
          <strong key={i} className="font-medium text-ink">
            {part.slice(2, -2)}
          </strong>
        );
      }
      const link = LINK.exec(part);
      if (link) {
        const [, label, url] = link;
        if (!isHttpsUrl(url)) return label;
        return (
          <a
            key={i}
            href={url}
            target="_blank"
            rel="noopener noreferrer nofollow"
            referrerPolicy="no-referrer"
            className="text-accent underline decoration-[color-mix(in_oklab,var(--color-accent)_45%,transparent)] underline-offset-2 hover:decoration-accent"
          >
            {label}
            <ExternalLink aria-hidden className="ml-0.5 inline size-3 align-baseline" />
          </a>
        );
      }
      return part;
    });
}

export function Prose({ text }: { text: string }) {
  return (
    <div className="flex flex-col gap-4">
      {parseBlocks(text).map((block, i) => {
        if (block.kind === "list") {
          return (
            <ul key={i} className="flex flex-col gap-2 pl-5 [list-style:disc] marker:text-muted">
              {block.items.map((item, j) => (
                <li key={j}>{renderInline(item)}</li>
              ))}
            </ul>
          );
        }
        if (block.kind === "ordered") {
          return (
            <ol key={i} start={block.start} className="flex flex-col gap-2 pl-6 [list-style:decimal] marker:text-muted">
              {block.items.map((item, j) => (
                <li key={j}>{renderInline(item)}</li>
              ))}
            </ol>
          );
        }
        return <p key={i}>{renderInline(block.text)}</p>;
      })}
    </div>
  );
}
