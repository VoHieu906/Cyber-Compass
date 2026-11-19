import type { ReactNode } from "react";

/** The frame shared by the login, register and password screens: one task, one panel. */
export function AuthPanel({ title, lede, children, footer }: { title: string; lede?: ReactNode; children: ReactNode; footer?: ReactNode }) {
  return (
    <div className="mx-auto flex w-full max-w-[26rem] flex-col gap-6">
      <header className="flex flex-col gap-2">
        <h1 className="text-[length:var(--text-2xl)]">{title}</h1>
        {lede ? <p className="text-ink-2">{lede}</p> : null}
      </header>
      <div className="panel p-6 sm:p-7">{children}</div>
      {footer ? <div className="text-sm">{footer}</div> : null}
    </div>
  );
}

export function TextLink({ href, children }: { href: string; children: ReactNode }) {
  return (
    <a
      href={href}
      className="rounded-chip text-ink underline decoration-rule-2 underline-offset-4 transition-[text-decoration-color] duration-[var(--dur-micro)] hover:decoration-accent"
    >
      {children}
    </a>
  );
}
