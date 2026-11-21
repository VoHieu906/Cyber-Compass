import { z } from "zod";
import "@/lib/zod-config";

/**
 * Client-side rules mirror the API's (backend Form Requests) for earlier feedback.
 * The API stays the authority; its messages are shown when they disagree.
 */
type T = (key: string, values?: Record<string, string | number>) => string;

const password = (t: T) =>
  z
    .string()
    .min(8, t("passwordMin", { min: 8 }))
    .max(128)
    .regex(/[A-Za-z]/, t("passwordLetter"))
    .regex(/[0-9]/, t("passwordNumber"));

export const loginSchema = (t: T) =>
  z.object({
    email: z.email(t("emailInvalid")),
    password: z.string().min(1, t("required")),
    remember: z.boolean(),
  });

export const registerSchema = (t: T) =>
  z
    .object({
      name: z.string().trim().min(1, t("required")).max(120),
      email: z.email(t("emailInvalid")),
      password: password(t),
      password_confirmation: z.string(),
    })
    .refine((v) => v.password === v.password_confirmation, {
      path: ["password_confirmation"],
      message: t("passwordMismatch"),
    });

export const forgotPasswordSchema = (t: T) => z.object({ email: z.email(t("emailInvalid")) });

export const resetPasswordSchema = (t: T) =>
  z
    .object({
      password: password(t),
      password_confirmation: z.string(),
    })
    .refine((v) => v.password === v.password_confirmation, {
      path: ["password_confirmation"],
      message: t("passwordMismatch"),
    });

export const changePasswordSchema = (t: T) =>
  z
    .object({
      current_password: z.string().min(1, t("required")),
      password: password(t),
      password_confirmation: z.string(),
    })
    .refine((v) => v.password === v.password_confirmation, {
      path: ["password_confirmation"],
      message: t("passwordMismatch"),
    })
    .refine((v) => v.password !== v.current_password, {
      path: ["password"],
      message: t("passwordSameAsCurrent"),
    });
