import type { ReactNode } from "react";

/**
 * Renders the lesson prose format: blank-line separated paragraphs, "- " bullet
 * lists, `inline code` and **bold**. Everything becomes React text nodes, never
 * HTML, so content cannot inject markup or script.
 */

type Block = { kind: "paragraph"; text: string } | { kind: "list"; items: string[] };

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
      return { kind: "paragraph", text: lines.join(" ") };
    });
}

export function renderInline(text: string): ReactNode[] {
  return text
    .split(/(`[^`]+`|\*\*[^*]+\*\*)/g)
    .filter((part) => part !== "")
    .map((part, i) => {
      if (part.startsWith("`") && part.endsWith("`") && part.length > 2) {
        return (
          <code key={i} className="measured rounded-chip border border-rule bg-paper-3 px-1 py-px text-[0.9em] text-ink">
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
      return part;
    });
}

export function Prose({ text }: { text: string }) {
  return (
    <div className="flex flex-col gap-4">
      {parseBlocks(text).map((block, i) =>
        block.kind === "list" ? (
          <ul key={i} className="flex flex-col gap-2 pl-5 [list-style:disc] marker:text-muted">
            {block.items.map((item, j) => (
              <li key={j}>{renderInline(item)}</li>
            ))}
          </ul>
        ) : (
          <p key={i}>{renderInline(block.text)}</p>
        ),
      )}
    </div>
  );
}
