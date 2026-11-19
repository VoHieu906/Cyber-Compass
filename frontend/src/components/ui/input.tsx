"use client";

import { Eye, EyeOff } from "lucide-react";
import { useId, useState, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export function controlClasses(invalid: boolean, className?: string) {
  return cn(
    "h-10 w-full min-w-0 rounded-control border bg-paper-3 px-3 text-ink placeholder:text-muted",
    "transition-[border-color] duration-[var(--dur-micro)] ease-out",
    "hover:border-muted disabled:cursor-not-allowed disabled:opacity-50 read-only:bg-paper read-only:hover:border-field-edge",
    invalid ? "border-bad" : "border-field-edge",
    className,
  );
}

type ShellProps = {
  id: string;
  label: string;
  error?: string;
  hint?: string;
  /** Rendered beside the label, e.g. a "Forgot password?" link. */
  aside?: ReactNode;
  children: (describedBy: string | undefined) => ReactNode;
};

/** Label, control and message wired together. Errors are text plus colour, never colour alone. */
function FieldShell({ id, label, error, hint, aside, children }: ShellProps) {
  const messageId = `${id}-message`;
  const message = error ?? hint;
  return (
    <div className="flex min-w-0 flex-col gap-1.5">
      <div className="flex items-baseline justify-between gap-3">
        <label htmlFor={id} className="text-sm font-medium text-ink">
          {label}
        </label>
        {aside}
      </div>
      {children(message ? messageId : undefined)}
      {message ? (
        <p id={messageId} className={cn("text-sm", error ? "text-bad" : "text-muted")}>
          {message}
        </p>
      ) : null}
    </div>
  );
}

type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  error?: string;
  hint?: string;
  aside?: ReactNode;
};

export function Field({ label, error, hint, aside, id, className, ...props }: FieldProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  return (
    <FieldShell id={inputId} label={label} error={error} hint={hint} aside={aside}>
      {(describedBy) => (
        <input
          id={inputId}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          className={controlClasses(Boolean(error), className)}
          {...props}
        />
      )}
    </FieldShell>
  );
}

type PasswordFieldProps = Omit<FieldProps, "type"> & { showLabel: string; hideLabel: string };

export function PasswordField({ label, error, hint, aside, id, className, showLabel, hideLabel, ...props }: PasswordFieldProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const [visible, setVisible] = useState(false);
  return (
    <FieldShell id={inputId} label={label} error={error} hint={hint} aside={aside}>
      {(describedBy) => (
        <div className="relative">
          <input
            id={inputId}
            type={visible ? "text" : "password"}
            aria-invalid={error ? true : undefined}
            aria-describedby={describedBy}
            className={controlClasses(Boolean(error), cn("pr-11", className))}
            {...props}
          />
          <button
            type="button"
            onClick={() => setVisible((v) => !v)}
            aria-label={visible ? hideLabel : showLabel}
            aria-pressed={visible}
            className="absolute inset-y-0 right-0 flex w-10 items-center justify-center rounded-r-control text-muted transition-[color] duration-[var(--dur-micro)] hover:text-ink"
          >
            {visible ? <EyeOff aria-hidden className="size-4" /> : <Eye aria-hidden className="size-4" />}
          </button>
        </div>
      )}
    </FieldShell>
  );
}

type SelectFieldProps = SelectHTMLAttributes<HTMLSelectElement> & {
  label: string;
  error?: string;
  hint?: string;
  options: readonly { value: string; label: string }[];
  placeholder?: string;
};

export function SelectField({ label, error, hint, id, className, options, placeholder, ...props }: SelectFieldProps) {
  const autoId = useId();
  const selectId = id ?? autoId;
  return (
    <FieldShell id={selectId} label={label} error={error} hint={hint}>
      {(describedBy) => (
        <select
          id={selectId}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy}
          className={controlClasses(Boolean(error), cn("appearance-auto pr-2", className))}
          {...props}
        >
          {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
          {options.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
      )}
    </FieldShell>
  );
}

type CheckboxProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & { label: string };

export function Checkbox({ label, id, className, ...props }: CheckboxProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  return (
    <div className={cn("flex items-center gap-2", className)}>
      <input
        id={inputId}
        type="checkbox"
        className="size-4 shrink-0 rounded-chip border-field-edge accent-[var(--color-accent)]"
        {...props}
      />
      <label htmlFor={inputId} className="text-sm text-ink">
        {label}
      </label>
    </div>
  );
}
