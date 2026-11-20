"use client";

import { Search, SearchX } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useId, useState } from "react";
import { Avatar } from "@/components/shared/avatar";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { controlClasses } from "@/components/ui/input";
import { Pagination } from "@/components/ui/pagination";
import { useAdminUsers } from "../hooks";
import { DEFAULT_USER_QUERY, parseUserQuery, userQueryToSearch } from "../query-state";
import { USER_SORTS, type AdminUser, type UserQuery } from "../types";
import { RoleBadge, StatusBadge } from "./badges";

const SEARCH_DEBOUNCE_MS = 300;

export function UsersView() {
  const t = useTranslations("admin.users");
  const tRoles = useTranslations("admin.roles");
  const tStatus = useTranslations("admin.status");
  const router = useRouter();
  const pathname = usePathname();
  const query = parseUserQuery(useSearchParams());
  const users = useAdminUsers(query);
  const searchId = useId();
  const [search, setSearch] = useState(query.q);

  function update(patch: Partial<UserQuery>) {
    const next = { ...query, ...patch, page: patch.page ?? 1 };
    router.replace(`${pathname}${userQueryToSearch(next)}`, { scroll: false });
  }

  useEffect(() => {
    if (search.trim() === query.q.trim()) return;
    const timer = setTimeout(() => update({ q: search }), SEARCH_DEBOUNCE_MS);
    return () => clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- only the typed text should restart the timer
  }, [search]);

  const page = users.data;
  const filtered = Boolean(query.q.trim() || query.role || query.status);
  const select = "w-full sm:w-auto";

  return (
    <div className="flex flex-col gap-6">
      <form
        role="search"
        onSubmit={(e) => {
          e.preventDefault();
          update({ q: search });
        }}
        className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end"
      >
        <div className="relative min-w-0 flex-1 sm:min-w-[16rem]">
          <label htmlFor={searchId} className="sr-only">
            {t("search")}
          </label>
          <Search
            aria-hidden
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted"
          />
          <input
            id={searchId}
            type="search"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder={t("searchPlaceholder")}
            maxLength={100}
            className={controlClasses(false, "pl-9")}
          />
        </div>
        {/* Two filters per row on phones, one row with the search on wider screens. */}
        <div className="grid grid-cols-2 gap-3 sm:flex sm:flex-wrap sm:items-end">
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("role")}
            <select
              className={controlClasses(false, select)}
              value={query.role}
              onChange={(e) => update({ role: e.target.value as UserQuery["role"] })}
            >
              <option value="">{t("anyRole")}</option>
              <option value="learner">{tRoles("learner")}</option>
              <option value="admin">{tRoles("admin")}</option>
            </select>
          </label>
          <label className="flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("status")}
            <select
              className={controlClasses(false, select)}
              value={query.status}
              onChange={(e) => update({ status: e.target.value as UserQuery["status"] })}
            >
              <option value="">{t("anyStatus")}</option>
              <option value="active">{tStatus("active")}</option>
              <option value="suspended">{tStatus("suspended")}</option>
            </select>
          </label>
          <label className="col-span-2 flex flex-col gap-1.5 text-sm font-medium text-ink">
            {t("sort")}
            <select
              className={controlClasses(false, select)}
              value={query.sort}
              onChange={(e) => update({ sort: e.target.value as UserQuery["sort"] })}
            >
              {USER_SORTS.map((s) => (
                <option key={s} value={s}>
                  {t(`sorts.${s}`)}
                </option>
              ))}
            </select>
          </label>
        </div>
      </form>

      <div className="flex min-h-6 flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-ink" aria-live="polite">
          {page ? t("results", { count: page.meta.total }) : null}
        </p>
        {filtered ? (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSearch("");
              router.replace(`${pathname}${userQueryToSearch({ ...DEFAULT_USER_QUERY, sort: query.sort })}`, {
                scroll: false,
              });
            }}
          >
            {t("clearFilters")}
          </Button>
        ) : null}
      </div>

      {users.isError ? (
        <div className="flex flex-col items-start gap-3">
          <Alert tone="error">{t("loadError")}</Alert>
          <Button variant="secondary" onClick={() => users.refetch()}>
            {t("retry")}
          </Button>
        </div>
      ) : !page ? (
        <div aria-hidden className="h-80 rounded-card bg-paper-3" />
      ) : page.data.length === 0 ? (
        <div className="panel flex flex-col items-start gap-2 p-8">
          <SearchX aria-hidden className="size-6 text-muted" />
          <h2 className="text-[length:var(--text-lg)]">{t("emptyTitle")}</h2>
          <p>{t("emptyBody")}</p>
        </div>
      ) : (
        <UserTable users={page.data} busy={users.isFetching} dimmed={users.isPlaceholderData} />
      )}

      {page ? (
        <Pagination
          page={page.meta.current_page}
          totalPages={page.meta.last_page}
          onChange={(p) => {
            update({ page: p });
            window.scrollTo({ top: 0 });
          }}
          labels={{
            nav: t("pagination"),
            previous: t("previous"),
            next: t("next"),
            page: t("page", { page: page.meta.current_page, total: page.meta.last_page }),
          }}
        />
      ) : null}
    </div>
  );
}

/**
 * A real table on wide screens (scannable columns, sortable from the controls above);
 * on phones each row collapses into a stacked card, keeping the same reading order.
 */
function UserTable({ users, busy, dimmed }: { users: AdminUser[]; busy: boolean; dimmed: boolean }) {
  const t = useTranslations("admin.users");
  const format = useFormatter();
  const date = (iso: string | null) => (iso ? format.dateTime(new Date(iso), { dateStyle: "medium" }) : null);

  return (
    <div className="panel overflow-hidden" aria-busy={busy} style={{ opacity: dimmed ? 0.6 : 1 }}>
      <table className="w-full border-collapse text-left text-sm">
        <thead className="max-md:sr-only">
          <tr className="border-b border-rule text-xs text-muted">
            <th scope="col" className="px-5 py-3 font-medium">
              {t("columns.user")}
            </th>
            <th scope="col" className="px-3 py-3 font-medium">
              {t("columns.role")}
            </th>
            <th scope="col" className="px-3 py-3 font-medium">
              {t("columns.status")}
            </th>
            <th scope="col" className="px-3 py-3 font-medium">
              {t("columns.joined")}
            </th>
            <th scope="col" className="px-5 py-3 font-medium">
              {t("columns.lastLogin")}
            </th>
          </tr>
        </thead>
        <tbody>
          {users.map((user) => (
            <tr
              key={user.id}
              className="relative border-b border-rule transition-[background-color] duration-[var(--dur-micro)] last:border-b-0 hover:bg-paper-3 max-md:grid max-md:grid-cols-[auto_1fr] max-md:gap-x-3 max-md:gap-y-2 max-md:p-4"
            >
              <td className="px-5 py-3 max-md:col-span-2 max-md:p-0">
                <div className="flex items-center gap-3">
                  <Avatar name={user.name} src={user.avatar_url} size={32} />
                  <div className="min-w-0">
                    {/* The stretched link makes the whole row clickable while staying one tab stop. */}
                    <Link
                      href={`/admin/users/${user.id}`}
                      className="font-medium text-ink after:absolute after:inset-0 focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-focus"
                    >
                      {user.name}
                    </Link>
                    {user.is_self ? <span className="ml-2 text-xs text-muted">{t("you")}</span> : null}
                    <p className="truncate text-muted">{user.email}</p>
                  </div>
                </div>
              </td>
              <td className="px-3 py-3 max-md:p-0">
                <RoleBadge role={user.role} />
              </td>
              <td className="px-3 py-3 max-md:p-0">
                <StatusBadge status={user.status} />
              </td>
              <td className="measured px-3 py-3 text-muted max-md:hidden">{date(user.created_at)}</td>
              <td className="measured px-5 py-3 text-muted max-md:col-span-2 max-md:p-0 max-md:text-xs">
                <span className="md:sr-only">{t("columns.lastLogin")}: </span>
                {date(user.last_login_at) ?? t("never")}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
