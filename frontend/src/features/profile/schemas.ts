import { z } from "zod";

type T = (key: string, values?: Record<string, string | number>) => string;

const today = () => new Date().toISOString().slice(0, 10);

/** Mirrors backend UpdateProfileRequest. Empty optional fields are sent as null. */
export const profileSchema = (t: T) =>
  z.object({
    name: z.string().trim().min(1, t("required")).max(120),
    phone: z
      .string()
      .trim()
      .max(32)
      .refine((v) => v === "" || /^\+?[0-9\s\-().]{6,32}$/.test(v), t("phoneInvalid")),
    date_of_birth: z
      .string()
      .refine((v) => v === "" || (v > "1900-01-01" && v < today()), t("dateInvalid")),
    education_level: z.enum(["", "high_school", "undergraduate", "graduate", "other"]),
  });

export type ProfileFormValues = z.infer<ReturnType<typeof profileSchema>>;
