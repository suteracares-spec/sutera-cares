import { router } from "expo-router";
import { CalendarX, ChevronLeft, ChevronRight, MessageCircleWarning } from "lucide-react-native";
import { Button, Card, Chip, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { Pressable, View } from "react-native";

import { EmptyState, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import type { OfficeDay, OfficeShift } from "@/lib/api";
import { addDays, clock, malaysiaDate, shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** The office's day: every caregiver, what needs someone now, and concerns. */
export default function OfficeToday(): JSX.Element {
  const [date, setDate] = useState(malaysiaDate());
  const { data, error, loading, reload } = useOffice<OfficeDay>(`/office/day?date=${date}`);
  const [accent, muted, danger] = useThemeColor(["accent", "muted", "danger"]);

  const today = malaysiaDate();
  const shifts = data?.shifts ?? [];
  const pick = (keep: (s: OfficeShift) => boolean) => shifts.filter(keep);
  const groups: [string, OfficeShift[]][] = [
    ["Needs attention", pick((s) => s.attention !== null)],
    ["On now", pick((s) => s.status === "in_progress")],
    ["Coming up", pick((s) => s.status === "scheduled" && s.attention === null)],
    ["Finished", pick((s) => ["completed", "missed", "cancelled"].includes(s.status))],
  ];

  return (
    <Screen
      title={date === today ? "Today" : shortDay(date)}
      subtitle={date === today ? shortDay(date) : "Office"}
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      <View className="flex-row items-center gap-2">
        <Button size="sm" variant="secondary" isIconOnly onPress={() => setDate(addDays(date, -1))}>
          <ChevronLeft size={18} color={accent} />
        </Button>
        <Button
          size="sm"
          variant={date === today ? "primary" : "secondary"}
          onPress={() => setDate(today)}
        >
          Today
        </Button>
        <Button size="sm" variant="secondary" isIconOnly onPress={() => setDate(addDays(date, 1))}>
          <ChevronRight size={18} color={accent} />
        </Button>
      </View>

      {error && <ErrorBanner title="Could not load" message={`${error} Pull down to try again.`} />}

      {!data && loading ? (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      ) : data ? (
        <>
          <View className="flex-row gap-3">
            <Stat value={data.counts.total} label="Shifts" />
            <Stat
              value={data.counts.attention}
              label="Attention"
              tone={data.counts.attention ? "danger" : undefined}
            />
            <Stat value={data.counts.on_now} label="On now" tone="accent" />
            <Stat value={data.counts.done} label="Done" tone="success" />
          </View>

          {data.open_concerns > 0 && (
            <Pressable onPress={() => router.push("/office/concerns")} accessibilityRole="button">
              <Card className="flex-row items-center gap-3 border border-danger-soft">
                <MessageCircleWarning size={24} color={danger} />
                <View className="flex-1">
                  <Typography weight="semibold">
                    {data.open_concerns} open {data.open_concerns === 1 ? "concern" : "concerns"}
                  </Typography>
                  <Typography type="body-sm" color="muted">
                    From caregivers and families
                  </Typography>
                </View>
                <ChevronRight size={20} color={muted} />
              </Card>
            </Pressable>
          )}

          {shifts.length === 0 && (
            <EmptyState icon={CalendarX} title="No shifts booked on this day" />
          )}
          {groups.map(
            ([title, list]) =>
              list.length > 0 && (
                <Section key={title} title={title}>
                  {list.map((s) => (
                    <OfficeShiftCard key={s.id} shift={s} />
                  ))}
                </Section>
              )
          )}
        </>
      ) : null}
    </Screen>
  );
}

function Stat({
  value,
  label,
  tone,
}: {
  value: number;
  label: string;
  tone?: "danger" | "accent" | "success";
}) {
  const color = tone ? `text-${tone}` : "text-foreground";
  return (
    <Card className="flex-1 items-center py-3 px-1">
      <Typography type="h4" weight="bold" className={color}>
        {value}
      </Typography>
      <Typography type="body-xs" color="muted">
        {label}
      </Typography>
    </Card>
  );
}

function OfficeShiftCard({ shift }: { shift: OfficeShift }) {
  const status = shift.attention ?? shift.status;
  const label = shift.status === "in_progress" ? `In since ${clock(shift.check_in_at)}` : undefined;

  return (
    <Pressable
      onPress={() =>
        router.push({ pathname: "/office/shift/[id]", params: { id: String(shift.id) } })
      }
      accessibilityRole="button"
    >
      <Card className={shift.status === "cancelled" ? "opacity-60" : undefined}>
        <Card.Body className="gap-1">
          <View className="flex-row items-center justify-between">
            <Typography weight="bold">
              {shift.start}–{shift.end}
            </Typography>
            {status !== "scheduled" && <StatusChip status={status} label={label} />}
          </View>
          <Typography.Heading type="h5">{shift.client}</Typography.Heading>
          <Typography type="body-sm" color="muted">
            {shift.caregiver ?? "Nobody assigned"}
            {shift.covering ? " (covering)" : ""}
            {shift.area ? ` · ${shift.area}` : ""}
          </Typography>
          {shift.concern && (
            <Chip variant="soft" color="warning" size="sm" className="self-start mt-1">
              <Chip.Label>Concern flagged</Chip.Label>
            </Chip>
          )}
        </Card.Body>
      </Card>
    </Pressable>
  );
}
