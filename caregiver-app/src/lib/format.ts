// Dates and times written out by hand, always in Malaysia time (UTC+8,
// no daylight saving). The office books and records visits in Malaysian
// time, so the app shows the same, whatever the phone's own clock or
// language settings are set to.

const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const LONG_DAYS = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const MALAYSIA_OFFSET_MS = 8 * 60 * 60 * 1000;

/** A moment, shifted so its UTC fields read as Malaysian wall-clock time. */
function inMalaysia(moment: Date): Date {
  return new Date(moment.getTime() + MALAYSIA_OFFSET_MS);
}

/** "2026-10-05" -> a date whose UTC fields are that calendar day. */
function calendarDay(day: string): Date {
  const [y, m, d] = day.split("-").map(Number);
  return new Date(Date.UTC(y, m - 1, d));
}

/** "Mon 5 Oct" */
export function shortDay(day: string): string {
  const d = calendarDay(day);
  return `${DAYS[d.getUTCDay()]} ${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]}`;
}

/** "Monday 5 Oct", today in Malaysia. */
export function longToday(): string {
  const d = inMalaysia(new Date());
  return `${LONG_DAYS[d.getUTCDay()]} ${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]}`;
}

/** An ISO timestamp -> "08:02", Malaysian time. */
export function clock(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = inMalaysia(new Date(iso));
  return `${String(d.getUTCHours()).padStart(2, "0")}:${String(d.getUTCMinutes()).padStart(2, "0")}`;
}

export function firstName(name: string): string {
  return name.split(" ")[0] ?? name;
}
