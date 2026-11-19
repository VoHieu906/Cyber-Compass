import { useId, type InputHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

type FieldProps = InputHTMLAttributes<HTMLInputElement> & {
  label: string;
  error?: string;
  hint?: string;
};

/** Label, input and message wired together. Errors are text plus colour, never colour alone. */
export function Field({ label, error, hint, id, className, ...props }: FieldProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const messageId = `${inputId}-message`;
  const message = error ?? hint;

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={inputId} className="text-sm font-medium text-ink">
        {label}
      </label>
      <input
        id={inputId}
        aria-invalid={error ? true : undefined}
        aria-describedby={message ? messageId : undefined}
        className={cn(
          "h-10 rounded-control border bg-paper-3 px-3 text-ink placeholder:text-muted",
          "transition-[border-color] duration-[var(--dur-micro)] ease-out",
          "hover:border-muted disabled:cursor-not-allowed disabled:opacity-50",
          error ? "border-bad" : "border-field-edge",
          className,
        )}
        {...props}
      />
      {message ? (
        <p id={messageId} className={cn("text-sm", error ? "text-bad" : "text-muted")}>
          {message}
        </p>
      ) : null}
    </div>
  );
}
