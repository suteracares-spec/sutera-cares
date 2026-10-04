import { CalendarDays, Coffee } from "lucide-react-native";
import { Spinner, Typography } from "heroui-native";
import { useCallback, useEffect, useState, type JSX } from "react";
import { View } from "react-native";

import { ShiftCard } from "@/components/ShiftCard";
import { SyncBanner } from "@/components/SyncBanner";
import { EmptyState, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { useAuth } from "@/lib/auth";
import { clock, firstName, longToday } from "@/lib/format";
import { useVisits } from "@/lib/visits";

/** The caregiver's day: anything left open, today, and the week ahead. */
export default function MyShifts(): JSX.Element {
  const { profile } = useAuth();
  const { lists, refresh, lastSynced, pendingFor } = useVisits();
  const [refreshing, setRefreshing] = useState(false);

  const pull = useCallback(async () => {
    setRefreshing(true);
    await refresh();
    setRefreshing(false);
  }, [refresh]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const cards = (shifts: NonNullable<typeof lists>["today"], showDate: boolean) =>
    shifts.map((s) => (
      <ShiftCard key={s.id} shift={s} showDate={showDate} waiting={pendingFor(s.id).length > 0} />
    ));

  return (
    <Screen
      title={`Hello${profile ? `, ${firstName(profile.user.name)}` : ""}`}
      subtitle={longToday()}
      refreshing={refreshing}
      onRefresh={pull}
    >
      <SyncBanner />

      {!lists ? (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      ) : (
        <>
          {lists.unfinished.length > 0 && (
            <Section title="Not checked out">{cards(lists.unfinished, true)}</Section>
          )}

          <Section title="Today">
            {lists.today.length ? (
              cards(lists.today, false)
            ) : (
              <EmptyState icon={Coffee} title="No shifts today" />
            )}
          </Section>

          <Section title="Next 7 days">
            {lists.coming.length ? (
              cards(lists.coming, true)
            ) : (
              <EmptyState
                icon={CalendarDays}
                title="Nothing booked yet"
                hint="The office adds your shifts here."
              />
            )}
          </Section>
        </>
      )}

      {lastSynced && (
        <Typography type="body-xs" color="muted" align="center">
          Updated {clock(lastSynced)}. Pull down to refresh.
        </Typography>
      )}
    </Screen>
  );
}
