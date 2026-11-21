"use client";

import { KeyRound, ShieldCheck, ShieldMinus, UserCheck, UserX } from "lucide-react";
import { useFormatter, useTranslations } from "next-intl";
import { useId, useState, type ReactNode } from "react";
import { Avatar } from "@/components/shared/avatar";
import { Breadcrumb } from "@/components/shared/breadcrumb";
import { Alert } from "@/components/ui/alert";
import { Button } from "@/components/ui/button";
import { Dialog } from "@/components/ui/dialog";
import { controlClasses } from "@/components/ui/input";
import { useErrorMessage } from "@/features/auth/use-error-message";
import { ApiError } from "@/lib/api/client";
import { useAdminUser, useChangeRole, useReactivateUser, useSendPasswordReset, useSuspendUser } from "../hooks";
import type { AdminUserDetail } from "../types";
import { AuditList } from "./audit-list";
import { RoleBadge, StatusBadge } from "./badges";

type DialogKind = "role" | "suspend" | "reset" | null;

const REASON_MIN = 5;
const REASON_MAX = 300;

export function UserDetailView({ id }: { id: number }) {
  const t = useTranslations("admin.user");
  const tUsers = useTranslations("admin.users");
  const tCommon = useTranslations("common");
  const user = useAdminUser(id);

  if (user.isError) {
    const missing = user.error instanceof ApiError && user.error.status === 404;
    return (
      <div className="flex flex-col items-start gap-3">
        <Alert tone="error">{missing ? t("notFound") : t("loadError")}</Alert>
        {missing ? null : (
          <Button variant="secondary" onClick={() => user.refetch()}>
            {t("retry")}
          </Button>
        )}
      </div>
    );
  }

  if (!user.data) return <div aria-hidden className="h-96 rounded-card bg-paper-3" />;

  return (
    <div className="flex flex-col gap-8">
      <Breadcrumb
        items={[{ href: "/admin/users", label: tUsers("title") }, { label: user.data.name }]}
        label={tCommon("breadcrumb")}
      />
      <Profile user={user.data} />
      <Actions user={user.data} />
      <section aria-labelledby="user-activity" className="flex flex-col gap-4">
        <h2 id="user-activity" className="text-[length:var(--text-lg)]">
          {t("activity")}
        </h2>
        <div className="panel p-5">
          <AuditList entries={user.data.recent_activity} empty={t("noActivity")} linkSubjects={false} />
        </div>
      </section>
    </div>
  );
}

function Profile({ user }: { user: AdminUserDetail }) {
  const t = useTranslations("admin.user");
  const tLocale = useTranslations("preferences");
  const format = useFormatter();
  const date = (iso: string | null) =>
    iso ? format.dateTime(new Date(iso), { dateStyle: "medium", timeStyle: "short" }) : null;

  const facts: { label: string; value: ReactNode; measured?: boolean }[] = [
    { label: t("facts.joined"), value: date(user.created_at), measured: true },
    { label: t("facts.lastLogin"), value: date(user.last_login_at) ?? t("never"), measured: true },
    { label: t("facts.emailVerified"), value: user.email_verified ? t("yes") : t("no") },
    { label: t("facts.language"), value: user.locale === "vi" ? tLocale("languageVi") : tLocale("languageEn") },
    { label: t("facts.enrollments"), value: user.stats.enrollments, measured: true },
    { label: t("facts.completedLessons"), value: user.stats.completed_lessons, measured: true },
  ];

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center gap-4">
        <Avatar name={user.name} src={user.avatar_url} size={56} />
        <div className="flex min-w-0 flex-col gap-1">
          <h1 className="text-[length:var(--text-xl)]">
            {user.name}
            {user.is_self ? <span className="ml-2 align-middle text-sm font-normal text-muted">{t("you")}</span> : null}
          </h1>
          <p className="truncate">{user.email}</p>
        </div>
        <div className="flex items-center gap-3 sm:ml-auto">
          <RoleBadge role={user.role} />
          <StatusBadge status={user.status} />
        </div>
      </div>

      {user.suspension ? (
        <Alert tone="error">
          {t("suspendedSince", { date: date(user.suspension.at) ?? "" })}
          {user.suspension.reason ? <> {t("suspendedReason", { reason: user.suspension.reason })}</> : null}
        </Alert>
      ) : null}

      <dl className="panels grid-cols-2 lg:grid-cols-3">
        {facts.map(({ label, value, measured }) => (
          <div key={label} className="flex flex-col gap-1 p-4">
            <dt className="text-xs">{label}</dt>
            <dd className={measured ? "measured text-ink" : "text-ink"}>{value}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

/**
 * Account actions. Each one says what it will do before it does it, and the reasons an
 * action is unavailable (your own account, the last admin) are shown, not hidden.
 */
function Actions({ user }: { user: AdminUserDetail }) {
  const t = useTranslations("admin.user");
  const [dialog, setDialog] = useState<DialogKind>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const reactivate = useReactivateUser(user.id);
  const errorMessage = useErrorMessage();

  // The server enforces the same rules; these only explain them up front.
  const protectedReason = user.is_self
    ? t("protected.self")
    : user.is_last_active_admin
      ? t("protected.lastAdmin")
      : null;
  const suspended = user.status === "suspended";

  return (
    <section aria-labelledby="user-actions" className="flex flex-col gap-4">
      <h2 id="user-actions" className="text-[length:var(--text-lg)]">
        {t("actions")}
      </h2>
      {notice ? <Alert tone="success">{notice}</Alert> : null}
      {reactivate.isError ? <Alert tone="error">{errorMessage(reactivate.error)}</Alert> : null}

      <ul className="panels grid-cols-1 md:grid-cols-3">
        <ActionCard
          icon={user.role === "admin" ? <ShieldMinus aria-hidden /> : <ShieldCheck aria-hidden />}
          title={user.role === "admin" ? t("role.demoteTitle") : t("role.promoteTitle")}
          body={user.role === "admin" ? t("role.demoteBody") : t("role.promoteBody")}
          blocked={user.role === "admin" ? protectedReason : null}
        >
          <Button
            variant="secondary"
            size="sm"
            disabled={user.role === "admin" && Boolean(protectedReason)}
            onClick={() => setDialog("role")}
          >
            {user.role === "admin" ? t("role.demote") : t("role.promote")}
          </Button>
        </ActionCard>

        <ActionCard
          icon={suspended ? <UserCheck aria-hidden /> : <UserX aria-hidden />}
          title={suspended ? t("reactivate.title") : t("suspend.title")}
          body={suspended ? t("reactivate.body") : t("suspend.body")}
          blocked={suspended ? null : protectedReason}
        >
          {suspended ? (
            <Button
              variant="secondary"
              size="sm"
              loading={reactivate.isPending}
              onClick={() => reactivate.mutate(undefined, { onSuccess: () => setNotice(t("reactivate.done")) })}
            >
              {t("reactivate.action")}
            </Button>
          ) : (
            <Button variant="danger" size="sm" disabled={Boolean(protectedReason)} onClick={() => setDialog("suspend")}>
              {t("suspend.action")}
            </Button>
          )}
        </ActionCard>

        <ActionCard
          icon={<KeyRound aria-hidden />}
          title={t("reset.title")}
          body={t("reset.body")}
          blocked={suspended ? t("reset.blockedSuspended") : null}
        >
          <Button variant="secondary" size="sm" disabled={suspended} onClick={() => setDialog("reset")}>
            {t("reset.action")}
          </Button>
        </ActionCard>
      </ul>

      <RoleDialog user={user} open={dialog === "role"} onClose={() => setDialog(null)} onDone={setNotice} />
      <SuspendDialog user={user} open={dialog === "suspend"} onClose={() => setDialog(null)} onDone={setNotice} />
      <ResetDialog user={user} open={dialog === "reset"} onClose={() => setDialog(null)} onDone={setNotice} />
    </section>
  );
}

function ActionCard({
  icon,
  title,
  body,
  blocked,
  children,
}: {
  icon: ReactNode;
  title: string;
  body: string;
  blocked: string | null;
  children: ReactNode;
}) {
  return (
    <li className="flex flex-col gap-3 p-5 [&>svg]:size-5 [&>svg]:text-muted">
      {icon}
      <div className="flex flex-col gap-1">
        <h3 className="text-base font-medium text-ink">{title}</h3>
        <p className="text-sm">{body}</p>
      </div>
      <div className="mt-auto flex flex-col items-start gap-2 pt-1">
        {children}
        {blocked ? <p className="text-xs text-muted">{blocked}</p> : null}
      </div>
    </li>
  );
}

type DialogProps = { user: AdminUserDetail; open: boolean; onClose: () => void; onDone: (message: string) => void };

function RoleDialog({ user, open, onClose, onDone }: DialogProps) {
  const t = useTranslations("admin.user");
  const tCommon = useTranslations("common");
  const change = useChangeRole(user.id);
  const errorMessage = useErrorMessage();
  const promoting = user.role !== "admin";

  return (
    <Dialog
      open={open}
      onClose={() => {
        change.reset();
        onClose();
      }}
      title={
        promoting
          ? t("role.promoteConfirmTitle", { name: user.name })
          : t("role.demoteConfirmTitle", { name: user.name })
      }
      description={promoting ? t("role.promoteConfirmBody") : t("role.demoteConfirmBody")}
      closeLabel={tCommon("close")}
    >
      {change.isError ? <Alert tone="error">{errorMessage(change.error)}</Alert> : null}
      <div className="flex flex-wrap justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>
          {tCommon("cancel")}
        </Button>
        <Button
          loading={change.isPending}
          onClick={() =>
            change.mutate(promoting ? "admin" : "learner", {
              onSuccess: () => {
                onDone(promoting ? t("role.promoted", { name: user.name }) : t("role.demoted", { name: user.name }));
                onClose();
              },
            })
          }
        >
          {promoting ? t("role.promote") : t("role.demote")}
        </Button>
      </div>
    </Dialog>
  );
}

function SuspendDialog({ user, open, onClose, onDone }: DialogProps) {
  const t = useTranslations("admin.user");
  const tCommon = useTranslations("common");
  const suspend = useSuspendUser(user.id);
  const errorMessage = useErrorMessage();
  const reasonId = useId();
  const hintId = useId();
  const [reason, setReason] = useState("");
  const [touched, setTouched] = useState(false);
  const tooShort = reason.trim().length < REASON_MIN;

  function close() {
    setReason("");
    setTouched(false);
    suspend.reset();
    onClose();
  }

  return (
    <Dialog
      open={open}
      onClose={close}
      title={t("suspend.confirmTitle", { name: user.name })}
      description={t("suspend.confirmBody")}
      closeLabel={tCommon("close")}
    >
      <form
        className="flex flex-col gap-4"
        onSubmit={(e) => {
          e.preventDefault();
          setTouched(true);
          if (tooShort) return;
          suspend.mutate(reason.trim(), {
            onSuccess: () => {
              onDone(t("suspend.done", { name: user.name }));
              close();
            },
          });
        }}
      >
        <div className="flex flex-col gap-1.5">
          <label htmlFor={reasonId} className="text-sm font-medium text-ink">
            {t("suspend.reason")}
          </label>
          <textarea
            id={reasonId}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            onBlur={() => setTouched(true)}
            maxLength={REASON_MAX}
            rows={3}
            aria-invalid={touched && tooShort}
            aria-describedby={hintId}
            className={controlClasses(touched && tooShort, "h-auto py-2")}
          />
          <p id={hintId} className={`text-xs ${touched && tooShort ? "text-bad" : ""}`}>
            {touched && tooShort
              ? t("suspend.reasonTooShort", { min: REASON_MIN })
              : t("suspend.reasonHint", { max: REASON_MAX })}
          </p>
        </div>
        {suspend.isError ? <Alert tone="error">{errorMessage(suspend.error)}</Alert> : null}
        <div className="flex flex-wrap justify-end gap-2">
          <Button variant="ghost" onClick={close}>
            {tCommon("cancel")}
          </Button>
          <Button type="submit" variant="danger" loading={suspend.isPending}>
            {t("suspend.action")}
          </Button>
        </div>
      </form>
    </Dialog>
  );
}

function ResetDialog({ user, open, onClose, onDone }: DialogProps) {
  const t = useTranslations("admin.user");
  const tCommon = useTranslations("common");
  const send = useSendPasswordReset(user.id);
  const errorMessage = useErrorMessage();

  return (
    <Dialog
      open={open}
      onClose={() => {
        send.reset();
        onClose();
      }}
      title={t("reset.confirmTitle")}
      description={t("reset.confirmBody", { email: user.email })}
      closeLabel={tCommon("close")}
    >
      {send.isError ? <Alert tone="error">{errorMessage(send.error)}</Alert> : null}
      <div className="flex flex-wrap justify-end gap-2">
        <Button variant="ghost" onClick={onClose}>
          {tCommon("cancel")}
        </Button>
        <Button
          loading={send.isPending}
          onClick={() =>
            send.mutate(undefined, {
              onSuccess: () => {
                onDone(t("reset.done", { email: user.email }));
                onClose();
              },
            })
          }
        >
          {t("reset.action")}
        </Button>
      </div>
    </Dialog>
  );
}
