import type { ReactNode } from "react";

/** One settings group: a heading, an optional line of context, and its controls. */
export function SettingsSection({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
  return (
    <section className="flex flex-col gap-5 border-b border-rule pb-8 last:border-b-0 last:pb-0">
      <header className="flex flex-col gap-1">
        <h2 className="text-[length:var(--text-lg)]">{title}</h2>
        {description ? <p className="text-sm">{description}</p> : null}
      </header>
      {children}
    </section>
  );
}
