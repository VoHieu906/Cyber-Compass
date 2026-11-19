/** Splits minutes into hours and minutes, for "1 h 30 min" style labels via i18n. */
export function splitMinutes(total: number): { hours: number; minutes: number } {
  const safe = Math.max(0, Math.round(total));
  return { hours: Math.floor(safe / 60), minutes: safe % 60 };
}
