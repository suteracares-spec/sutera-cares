import { CalendarX, ChevronLeft, ChevronRight } from "lucide-react-native";
import { Button, Chip, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { ScrollView, View } from "react-native";

import { OfficeShiftCard } from "@/components/OfficeShiftCard";
import { EmptyState, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import type { OfficeWeek } from "@/lib/api";
import { addDays, malaysiaDate, shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** The week across everyone, or one caregiver's week. */
export default function Schedule(): JSX.Element {
  const [week, setWeek] = useState(malaysiaDate());
  const [caregiver, setCaregiver] = useState<number | null>(null);
  const query = `/office/week?week=${week}${caregiver ? `&caregiver=${caregiver}` : ""}`;
  const { data, error, loading, reload } = useOffice<OfficeWeek>(query);
  const accent = useThemeColor("accent");
  const today = malaysiaDate();

  const monday = data?.week ?? week;
  const booked = data?.shifts.filter((s) => s.status !== "cancelled").length ?? 0;

  return (
    <Screen
      title="Schedule"
      subtitle={`Week of ${shortDay(monday)}${data ? ` · ${booked} shifts` : ""}`}
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      <View className="flex-row items-center gap-2">
        <Button
          size="sm"
          variant="secondary"
          isIconOnly
          onPress={() => setWeek(addDays(monday, -7))}
        >
          <ChevronLeft size={18} color={accent} />
        </Button>
        <Button size="sm" variant="secondary" onPress={() => setWeek(today)}>
          This week
        </Button>
        <Button
          size="sm"
          variant="secondary"
          isIconOnly
          onPress={() => setWeek(addDays(monday, 7))}
        >
          <ChevronRight size={18} color={accent} />
        </Button>
      </View>

      {data && data.caregivers.length > 0 && (
        <ScrollView
          horizontal
          showsHorizontalScrollIndicator={false}
          contentContainerClassName="gap-2"
        >
          <Chip
            variant={caregiver === null ? "primary" : "secondary"}
            onPress={() => setCaregiver(null)}
          >
            <Chip.Label>Everyone</Chip.Label>
          </Chip>
          {data.caregivers.map((c) => (
            <Chip
              key={c.id}
              variant={caregiver === c.id ? "primary" : "secondary"}
              onPress={() => setCaregiver(c.id)}
            >
              <Chip.Label>{c.name}</Chip.Label>
            </Chip>
          ))}
        </ScrollView>
      )}

      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!data && loading && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {data &&
        (data.shifts.length === 0 ? (
          <EmptyState icon={CalendarX} title="Nothing booked this week" />
        ) : (
          data.days.map((day) => {
            const shifts = data.shifts.filter((s) => s.date === day);
            return (
              <Section key={day} title={`${shortDay(day)}${day === today ? " · today" : ""}`}>
                {shifts.length === 0 ? (
                  <Typography color="muted" className="pl-1">
                    Nothing booked
                  </Typography>
                ) : (
                  shifts.map((s) => <OfficeShiftCard key={s.id} shift={s} />)
                )}
              </Section>
            );
          })
        ))}
    </Screen>
  );
}
