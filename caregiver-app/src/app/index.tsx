import { Redirect } from "expo-router";
import { Button, Spinner, Typography } from "heroui-native";
import { useCallback, useEffect, useState, type JSX } from "react";
import { RefreshControl, ScrollView, View } from "react-native";
import { SafeAreaView } from "@/components/SafeAreaView";

import { ShiftCard } from "@/components/ShiftCard";
import { SyncBanner } from "@/components/SyncBanner";
import type { Shift } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { clock, firstName, longToday } from "@/lib/format";
import { useVisits } from "@/lib/visits";

export default function ShiftsScreen(): JSX.Element {
  const { ready, token, profile, signOut } = useAuth();
  const { lists, refresh, lastSynced, pendingFor } = useVisits();
  const [refreshing, setRefreshing] = useState(false);

  const pull = useCallback(async () => {
    setRefreshing(true);
    await refresh();
    setRefreshing(false);
  }, [refresh]);

  useEffect(() => {
    if (token && !profile?.password_change_required && !profile?.user.office) refresh();
  }, [token, profile?.password_change_required, profile?.user.office, refresh]);

  if (!ready) {
    return (
      <View className="flex-1 bg-background items-center justify-center">
        <Spinner size="lg" />
      </View>
    );
  }
  if (!token) return <Redirect href="/login" />;
  if (profile?.password_change_required) return <Redirect href="/password" />;
  // Office accounts have no shifts of their own: they get the office overview.
  if (profile?.user.office) return <Redirect href="/office" />;

  const section = (title: string, shifts: Shift[], showDate: boolean, empty?: string) =>
    (shifts.length > 0 || empty) && (
      <View className="gap-3">
        <Typography
          type="body-xs"
          weight="bold"
          color="muted"
          className="uppercase tracking-widest mt-4"
        >
          {title}
        </Typography>
        {shifts.length === 0 ? (
          <Typography color="muted">{empty}</Typography>
        ) : (
          shifts.map((s) => (
            <ShiftCard
              key={s.id}
              shift={s}
              showDate={showDate}
              waiting={pendingFor(s.id).length > 0}
            />
          ))
        )}
      </View>
    );

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top"]}>
      <ScrollView
        contentContainerClassName="px-5 pb-10 gap-3"
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={pull} />}
      >
        <View className="flex-row items-start justify-between pt-4">
          <View className="flex-1">
            <Typography.Heading type="h2">
              Hello{profile ? `, ${firstName(profile.user.name)}` : ""}
            </Typography.Heading>
            <Typography color="muted">{longToday()}</Typography>
          </View>
          <Button size="sm" variant="ghost" onPress={signOut}>
            Sign out
          </Button>
        </View>

        <SyncBanner />

        {!lists ? (
          <View className="py-16 items-center">
            <Spinner size="lg" />
          </View>
        ) : (
          <>
            {section("Not checked out", lists.unfinished, true)}
            {section("Today", lists.today, false, "No shifts today.")}
            {section(
              "Next 7 days",
              lists.coming,
              true,
              "Nothing booked yet. The office adds your shifts here."
            )}
          </>
        )}

        {lastSynced && (
          <Typography type="body-xs" color="muted" align="center" className="mt-6">
            Updated {clock(lastSynced)}. Pull down to refresh.
          </Typography>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}
