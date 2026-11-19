import { cva, type VariantProps } from "class-variance-authority";
import { LoaderCircle } from "lucide-react";
import type { ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export const buttonVariants = cva(
  [
    "relative inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control border font-medium",
    "transition-[background-color,border-color,color] duration-[var(--dur-micro)] ease-out",
    "disabled:cursor-not-allowed disabled:opacity-50",
    "[&_svg]:size-4 [&_svg]:shrink-0",
  ],
  {
    variants: {
      variant: {
        primary:
          "border-accent bg-accent text-accent-ink hover:bg-[color-mix(in_oklab,var(--color-accent)_88%,var(--color-ink))] active:bg-[color-mix(in_oklab,var(--color-accent)_78%,var(--color-ink))]",
        secondary:
          "border-rule-2 bg-paper-2 text-ink hover:bg-paper-3 hover:border-[color-mix(in_oklab,var(--color-rule-2)_60%,var(--color-ink))] active:bg-paper",
        ghost: "border-transparent bg-transparent text-ink hover:bg-paper-3 active:bg-paper-2",
        danger:
          "border-[color-mix(in_oklab,var(--color-bad)_55%,var(--color-rule))] bg-paper-2 text-bad hover:bg-bad-soft active:bg-bad-soft",
      },
      size: {
        md: "h-10 px-4 text-sm",
        sm: "h-9 px-3 text-sm",
        icon: "size-10",
      },
    },
    defaultVariants: { variant: "primary", size: "md" },
  },
);

export type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
  VariantProps<typeof buttonVariants> & {
    loading?: boolean;
  };

export function Button({ className, variant, size, loading = false, disabled, children, type = "button", ...props }: ButtonProps) {
  return (
    <button
      type={type}
      className={cn(buttonVariants({ variant, size }), className)}
      disabled={disabled || loading}
      aria-busy={loading || undefined}
      {...props}
    >
      {loading ? <LoaderCircle aria-hidden className="animate-spin" /> : null}
      {children}
    </button>
  );
}
