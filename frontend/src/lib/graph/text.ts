/*
 * Label measuring without the DOM, shared by every graph layout. Kept apart from layout.ts so
 * pages that only size labels (the course map overview) never load dagre.
 */

/** Rough text width for laying out labels before they are drawn (no DOM measurement). */
export function textWidth(text: string, fontSize = 13): number {
  return Math.ceil(text.length * fontSize * 0.56);
}

/** Splits a label into at most `maxLines` lines of roughly `maxChars`, on word boundaries. */
export function wrapLabel(text: string, maxChars = 22, maxLines = 3): string[] {
  const lines: string[] = [];
  let line = "";
  for (const word of text.split(/\s+/).filter(Boolean)) {
    if (line && (line + " " + word).length > maxChars) {
      lines.push(line);
      line = word;
    } else {
      line = line ? `${line} ${word}` : word;
    }
  }
  if (line) lines.push(line);
  if (lines.length > maxLines) {
    const kept = lines.slice(0, maxLines);
    kept[maxLines - 1] = kept[maxLines - 1].replace(/.{0,1}$/, "…");
    return kept;
  }
  return lines;
}
