"use client";

import { LogOut, Settings, ShieldCheck } from "lucide-react";
import { useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useEffect, useId, useRef, useState } from "react";
import { Avatar } from "@/components/shared/avatar";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";
import { useCurrentUser, useLogout } from "../hooks";
import type { User } from "../types";

/** Header account area: log in / sign up for guests, an account menu for learners. */
export function AccountNav() {
  const t = useTranslations("nav");
  const { data: user, isPending } = useCurrentUser();
  const pathname = usePathname();

  // Reserve the space while the session is checked, so the header does not jump.
  if (isPending) return <span aria-hidden className="inline-block h-9 w-[8.5rem]" />;

  if (user) return <AccountMenu user={user} />;

  const next = pathname && !["/login", "/register", "/"].includes(pathname) ? `?next=${encodeURIComponent(pathname)}` : "";
  return (
    <div className="flex items-center gap-2">
      <Link href={`/login${next}`} className={buttonVariants({ variant: "ghost", size: "sm" })}>
        {t("login")}
      </Link>
      <Link href="/register" className={buttonVariants({ size: "sm", className: "max-[29.99rem]:hidden" })}>
        {t("register")}
      </Link>
    </div>
  );
}

/** A disclosure menu: a button that shows a short list of links. Closes on Escape, outside click and on choosing an item. */
function AccountMenu({ user }: { user: User }) {
  const t = useTranslations("nav");
  const logout = useLogout();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const menuId = useId();
  useEffect(() => {
    if (!open) return;
    const onPointer = (e: PointerEvent) => {
      if (!rootRef.current?.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    document.addEventListener("pointerdown", onPointer);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("pointerdown", onPointer);
      document.removeEventListener("keydown", onKey);
    };
  }, [open]);

  const item =
    "flex w-full items-center gap-2.5 rounded-[calc(var(--radius-control)-2px)] px-2.5 py-2 text-left text-sm text-ink transition-[background-color] duration-[var(--dur-micro)] hover:bg-paper-3 [&_svg]:size-4 [&_svg]:text-muted";

  return (
    <div ref={rootRef} className="relative">
      <button
        ref={buttonRef}
        type="button"
        aria-expanded={open}
        aria-controls={menuId}
        aria-label={`${t("account")}: ${user.name}`}
        onClick={() => setOpen((v) => !v)}
        className="flex items-center rounded-full"
      >
        <Avatar name={user.name} src={user.profile.avatar_url} size={34} />
      </button>
      <div
        id={menuId}
        hidden={!open}
        className="absolute right-0 top-[calc(100%+8px)] z-[var(--z-dropdown)] w-64 rounded-card border border-rule bg-paper-2 p-1.5 shadow-[var(--shadow-overlay)]"
      >
        <div className="flex items-center gap-3 border-b border-rule px-2.5 pt-1.5 pb-3">
          <Avatar name={user.name} src={user.profile.avatar_url} size={36} />
          <div className="min-w-0">
            <p className="truncate text-sm font-medium text-ink">{user.name}</p>
            <p className="truncate text-sm">{user.email}</p>
          </div>
        </div>
        <ul className="flex flex-col pt-1.5">
          <li>
            <Link href="/settings/profile" className={item} onClick={() => setOpen(false)}>
              <Settings aria-hidden />
              {t("settings")}
            </Link>
          </li>
          {user.role === "admin" ? (
            <li>
              <Link href="/admin" className={item} onClick={() => setOpen(false)}>
                <ShieldCheck aria-hidden />
                {t("admin")}
              </Link>
            </li>
          ) : null}
          <li>
            <button
              type="button"
              onClick={() => logout.mutate()}
              disabled={logout.isPending}
              className={cn(item, "disabled:opacity-50")}
            >
              <LogOut aria-hidden />
              {t("logout")}
            </button>
          </li>
        </ul>
      </div>
    </div>
  );
}
