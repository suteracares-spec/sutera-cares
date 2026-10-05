import { router, type Href } from "expo-router";
import {
  CalendarDays,
  CalendarX,
  ChevronLeft,
  ChevronRight,
  HeartHandshake,
  Inbox,
  MessageCircleWarning,
  Receipt,
  Users,
  type LucideIcon,
} from "lucide-react-native";
import { Card, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { Pressable, View } from "react-native";

import { TimelineShift } from "@/components/TimelineShift";
import { EmptyState, Initials, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import type { Enquiry, OfficeDay } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { addDays, firstName, greeting, longToday, malaysiaDate, shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** Sutera gold, from the logo: used sparingly, for what is waiting on the office. */
const GOLD = "#D9A441";

/**
 * The office's home: how the day is going at a glance, what needs someone
 * now, shortcuts to the rest of the office, and the day as a timeline.
 */
export default function OfficeToday(): JSX.Element {
  const { profile } = useAuth();
  const [date, setDate] = useState(malaysiaDate());
  const { data, error, loading, reload } = useOffice<OfficeDay>(`/office/day?date=${date}`);
  const { data: enquiries } = useOffice<{ new_count: number; enquiries: Enquiry[] }>(
    "/office/enquiries?show=open"
  );

  const today = malaysiaDate();
  const isToday = date === today;
  const shifts = data?.shifts ?? [];
  const attention = shifts.filter((s) => s.attention !== null);
  const timeline = shifts.filter((s) => s.attention === null);

  return (
    <Screen refreshing={loading && !!data} onRefresh={reload}>
      {/* Greeting */}
      <View className="flex-row items-center gap-3 pt-3">
        <View className="flex-1">
          <Typography color="muted">{longToday()}</Typography>
          <Typography.Heading type="h3">
            {greeting()}, {firstName(profile?.user.name ?? "")}
          </Typography.Heading>
        </View>
        <Pressable
          onPress={() => router.push("/office/more")}
          accessibilityRole="button"
          accessibilityLabel="Account and more"
        >
          <Initials name={profile?.user.name ?? "?"} size={48} />
        </Pressable>
      </View>

      {/* The day at a glance */}
      <DayHero
        date={date}
        isToday={isToday}
        counts={data?.counts}
        onPrev={() => setDate(addDays(date, -1))}
        onNext={() => setDate(addDays(date, 1))}
        onToday={() => setDate(today)}
      />

      {error && <ErrorBanner title="Could not load" message={`${error} Pull down to try again.`} />}

      {/* Urgent first */}
      {data && attention.length > 0 && (
        <Section title="Needs you now">
          <View>
            {attention.map((s, i) => (
              <TimelineShift key={s.id} shift={s} last={i === attention.length - 1} />
            ))}
          </View>
        </Section>
      )}

      {/* Concerns waiting on the office */}
      {data && data.open_concerns > 0 && (
        <Alert
          icon={MessageCircleWarning}
          color={GOLD}
          tone="gold"
          title={`${data.open_concerns} open ${data.open_concerns === 1 ? "concern" : "concerns"}`}
          detail="From caregivers and families"
          onPress={() => router.push("/office/concerns")}
        />
      )}

      {/* Shortcuts */}
      <View className="flex-row flex-wrap justify-between gap-y-4 py-1">
        <Shortcut icon={CalendarDays} label="Schedule" href="/office/schedule" />
        <Shortcut icon={Users} label="Clients" href="/office/clients" />
        <Shortcut icon={HeartHandshake} label="Caregivers" href="/office/caregivers" />
        <Shortcut icon={Receipt} label="Invoices" href="/office/billing" />
        <Shortcut
          icon={Inbox}
          label="Enquiries"
          href="/office/enquiries"
          badge={enquiries?.new_count}
        />
      </View>

      {!data && loading ? (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      ) : data ? (
        <>
          <Section title={isToday ? "Today's visits" : `Visits on ${shortDay(date)}`}>
            {shifts.length === 0 ? (
              <EmptyState icon={CalendarX} title="No visits booked on this day" />
            ) : timeline.length === 0 ? (
              <Typography color="muted">
                Every visit on this day needs attention: see above.
              </Typography>
            ) : (
              <View>
                {timeline.map((s, i) => (
                  <TimelineShift key={s.id} shift={s} last={i === timeline.length - 1} />
                ))}
              </View>
            )}
          </Section>
        </>
      ) : null}
    </Screen>
  );
}

/** The teal summary card: visits, progress through the day, and the day's arrows. */
function DayHero({
  date,
  isToday,
  counts,
  onPrev,
  onNext,
  onToday,
}: {
  date: string;
  isToday: boolean;
  counts: OfficeDay["counts"] | undefined;
  onPrev: () => void;
  onNext: () => void;
  onToday: () => void;
}) {
  const fg = useThemeColor("accent-foreground");
  const total = counts?.total ?? 0;
  const done = counts?.done ?? 0;
  const progress = total > 0 ? done / total : 0;

  return (
    <View className="bg-accent rounded-3xl p-5 gap-4 overflow-hidden">
      {/* Soft circles, as on the website's hero. */}
      <View className="absolute -right-10 -top-12 w-44 h-44 rounded-full bg-white/10" />
      <View className="absolute right-16 -bottom-16 w-32 h-32 rounded-full bg-white/5" />

      <View className="flex-row items-center justify-between">
        <Pressable
          onPress={onPrev}
          hitSlop={12}
          accessibilityRole="button"
          accessibilityLabel="Previous day"
          className="w-9 h-9 rounded-full bg-white/15 items-center justify-center"
        >
          <ChevronLeft size={18} color={fg} />
        </Pressable>
        <Pressable onPress={onToday} accessibilityRole="button" accessibilityLabel="Back to today">
          <Typography weight="semibold" style={{ color: fg }}>
            {isToday ? "Today" : shortDay(date)}
          </Typography>
          {!isToday && (
            <Typography type="body-xs" style={{ color: fg, opacity: 0.75 }} className="text-center">
              Tap for today
            </Typography>
          )}
        </Pressable>
        <Pressable
          onPress={onNext}
          hitSlop={12}
          accessibilityRole="button"
          accessibilityLabel="Next day"
          className="w-9 h-9 rounded-full bg-white/15 items-center justify-center"
        >
          <ChevronRight size={18} color={fg} />
        </Pressable>
      </View>

      <View className="flex-row items-end gap-3">
        <Typography style={{ color: fg, fontSize: 48, lineHeight: 52 }} weight="bold">
          {total}
        </Typography>
        <View className="pb-2">
          <Typography style={{ color: fg }} weight="semibold">
            {total === 1 ? "visit" : "visits"} booked
          </Typography>
          <Typography type="body-sm" style={{ color: fg, opacity: 0.8 }}>
            {total === 0 ? "Nothing on this day" : `${Math.round(progress * 100)}% done`}
          </Typography>
        </View>
      </View>

      <View className="h-2 rounded-full bg-white/20 overflow-hidden">
        <View
          className="h-2 rounded-full"
          style={{ width: `${progress * 100}%`, backgroundColor: GOLD }}
        />
      </View>

      <View className="flex-row gap-2">
        <HeroStat value={counts?.on_now ?? 0} label="On now" fg={fg} />
        <HeroStat
          value={counts?.attention ?? 0}
          label="Attention"
          fg={fg}
          alert={(counts?.attention ?? 0) > 0}
        />
        <HeroStat value={done} label="Done" fg={fg} />
      </View>
    </View>
  );
}

function HeroStat({
  value,
  label,
  fg,
  alert,
}: {
  value: number;
  label: string;
  fg: string;
  alert?: boolean;
}) {
  // Something needing the office stands out as a white tile with red figures.
  const danger = useThemeColor("danger");
  const color = alert ? danger : fg;
  return (
    <View
      className={`flex-1 rounded-2xl py-2.5 items-center ${alert ? "bg-white" : "bg-white/15"}`}
    >
      <Typography weight="bold" style={{ color, fontSize: 20 }}>
        {value}
      </Typography>
      <Typography type="body-xs" style={{ color, opacity: alert ? 1 : 0.85 }}>
        {label}
      </Typography>
    </View>
  );
}

/** A tinted strip for something waiting on the office. */
function Alert({
  icon: Icon,
  color,
  tone,
  title,
  detail,
  onPress,
}: {
  icon: LucideIcon;
  color: string;
  tone: "danger" | "gold";
  title: string;
  detail: string;
  onPress?: () => void;
}) {
  const muted = useThemeColor("muted");
  const body = (
    <Card
      className={tone === "danger" ? "bg-danger-soft" : ""}
      style={tone === "gold" ? { backgroundColor: "#D9A4411F" } : undefined}
    >
      <Card.Body className="flex-row items-center gap-3">
        <View
          className="w-10 h-10 rounded-full items-center justify-center"
          style={{ backgroundColor: `${color}26` }}
        >
          <Icon size={20} color={color} />
        </View>
        <View className="flex-1">
          <Typography weight="semibold">{title}</Typography>
          <Typography type="body-sm" color="muted">
            {detail}
          </Typography>
        </View>
        {onPress ? <ChevronRight size={20} color={muted} /> : null}
      </Card.Body>
    </Card>
  );
  return onPress ? (
    <Pressable onPress={onPress} accessibilityRole="button">
      {body}
    </Pressable>
  ) : (
    body
  );
}

/** A round shortcut with its label, and a count when something is new. */
function Shortcut({
  icon: Icon,
  label,
  href,
  badge,
}: {
  icon: LucideIcon;
  label: string;
  href: Href;
  badge?: number;
}) {
  const accent = useThemeColor("accent");
  return (
    <Pressable
      onPress={() => router.push(href)}
      accessibilityRole="button"
      accessibilityLabel={label}
      className="items-center gap-1.5 w-16"
    >
      <View className="w-14 h-14 rounded-2xl bg-accent-soft items-center justify-center">
        <Icon size={24} color={accent} />
        {badge ? (
          <View
            className="absolute -top-1 -right-1 min-w-5 h-5 px-1 rounded-full items-center justify-center"
            style={{ backgroundColor: GOLD }}
          >
            <Typography type="body-xs" weight="bold" style={{ color: "#ffffff" }}>
              {badge}
            </Typography>
          </View>
        ) : null}
      </View>
      <Typography type="body-xs" numberOfLines={1}>
        {label}
      </Typography>
    </Pressable>
  );
}
