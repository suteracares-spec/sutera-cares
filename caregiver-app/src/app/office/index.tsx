import { Redirect, router } from "expo-router";
import { Alert, Button, Card, Chip, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { Pressable, RefreshControl, ScrollView, View } from "react-native";

import { SafeAreaView } from "@/components/SafeAreaView";
import type { OfficeDay, OfficeShift } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { addDays, clock, firstName, malaysiaDate, shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** The office overview: one day across every caregiver. */
export default function OfficeDayScreen(): JSX.Element {
  const { token, profile, signOut } = useAuth();
  const [date, setDate] = useState(malaysiaDate());
  const { data, error, loading, reload } = useOffice<OfficeDay>(`/office/day?date=${date}`);

  if (!token) return <Redirect href="/login" />;
  if (profile && !profile.user.office) return <Redirect href="/" />;

  const today = malaysiaDate();
  const shifts = data?.shifts ?? [];
  const group = (keep: (s: OfficeShift) => boolean) => shifts.filter(keep);
  const attention = group((s) => s.attention !== null);
  const onNow = group((s) => s.status === "in_progress");
  const coming = group((s) => s.status === "scheduled" && s.attention === null);
  const finished = group((s) => ["completed", "missed", "cancelled"].includes(s.status));

  const section = (title: string, list: OfficeShift[]) =>
    list.length > 0 && (
      <View className="gap-3">
        <Typography
          type="body-xs"
          weight="bold"
          color="muted"
          className="uppercase tracking-widest mt-4"
        >
          {title}
        </Typography>
        {list.map((s) => (
          <OfficeShiftCard key={s.id} shift={s} />
        ))}
      </View>
    );

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top"]}>
      <ScrollView
        contentContainerClassName="px-5 pb-10 gap-3"
        refreshControl={<RefreshControl refreshing={loading && !!data} onRefresh={reload} />}
      >
        <View className="flex-row items-start justify-between pt-4">
          <View className="flex-1">
            <Typography.Heading type="h2">Office</Typography.Heading>
            <Typography color="muted">
              {profile ? `${firstName(profile.user.name)} · ` : ""}
              {date === today ? "Today" : shortDay(date)}
            </Typography>
          </View>
          <Button size="sm" variant="ghost" onPress={signOut}>
            Sign out
          </Button>
        </View>

        <View className="flex-row gap-2">
          <Button size="sm" variant="secondary" onPress={() => setDate(addDays(date, -1))}>
            ← Day before
          </Button>
          <Button
            size="sm"
            variant={date === today ? "primary" : "secondary"}
            onPress={() => setDate(today)}
          >
            Today
          </Button>
          <Button size="sm" variant="secondary" onPress={() => setDate(addDays(date, 1))}>
            Next →
          </Button>
        </View>

        {error && (
          <Alert status="danger">
            <Alert.Indicator />
            <Alert.Content>
              <Alert.Title>Could not load</Alert.Title>
              <Alert.Description>{error} Pull down to try again.</Alert.Description>
            </Alert.Content>
          </Alert>
        )}

        {!data && loading ? (
          <View className="py-16 items-center">
            <Spinner size="lg" />
          </View>
        ) : data ? (
          <>
            <View className="flex-row flex-wrap gap-2">
              <Chip variant="soft" color="default">
                <Chip.Label>{data.counts.total} shifts</Chip.Label>
              </Chip>
              {data.counts.attention > 0 && (
                <Chip variant="soft" color="danger">
                  <Chip.Label>{data.counts.attention} need attention</Chip.Label>
                </Chip>
              )}
              <Chip variant="soft" color="accent">
                <Chip.Label>{data.counts.on_now} on now</Chip.Label>
              </Chip>
              <Chip variant="soft" color="success">
                <Chip.Label>{data.counts.done} done</Chip.Label>
              </Chip>
            </View>

            <Pressable onPress={() => router.push("/office/concerns")} accessibilityRole="button">
              <Card variant={data.open_concerns > 0 ? "default" : "secondary"}>
                <Card.Body className="flex-row items-center justify-between">
                  <View>
                    <Card.Title>Concerns</Card.Title>
                    <Card.Description>
                      {data.open_concerns === 0
                        ? "Nothing open"
                        : `${data.open_concerns} open, from caregivers and families`}
                    </Card.Description>
                  </View>
                  <Typography color="muted">›</Typography>
                </Card.Body>
              </Card>
            </Pressable>

            {shifts.length === 0 && (
              <Typography color="muted" className="mt-6" align="center">
                No shifts booked on this day.
              </Typography>
            )}
            {section("Needs attention", attention)}
            {section("On now", onNow)}
            {section("Coming up", coming)}
            {section("Finished", finished)}
          </>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const STATUS_LABEL: Record<OfficeShift["status"], string> = {
  scheduled: "Booked",
  in_progress: "Checked in",
  completed: "Done",
  missed: "Missed",
  cancelled: "Cancelled",
};

function OfficeShiftCard({ shift }: { shift: OfficeShift }) {
  const chip =
    shift.attention === "no_show"
      ? { label: "Nobody came", color: "danger" as const }
      : shift.attention === "late"
        ? { label: "Not checked in", color: "danger" as const }
        : shift.status === "in_progress"
          ? { label: `In since ${clock(shift.check_in_at)}`, color: "accent" as const }
          : shift.status === "completed"
            ? { label: "Done", color: "success" as const }
            : shift.status === "scheduled"
              ? null
              : { label: STATUS_LABEL[shift.status], color: "default" as const };

  return (
    <Pressable
      onPress={() =>
        router.push({ pathname: "/office/shift/[id]", params: { id: String(shift.id) } })
      }
      accessibilityRole="button"
    >
      <Card className={shift.status === "cancelled" ? "opacity-60" : undefined}>
        <Card.Body className="gap-1">
          <Typography type="h6" weight="bold">
            {shift.start}–{shift.end}
          </Typography>
          <Typography.Heading type="h5">{shift.client}</Typography.Heading>
          <Typography type="body-sm" color="muted">
            {shift.caregiver ?? "Nobody assigned"}
            {shift.covering ? " (covering)" : ""}
            {shift.area ? ` · ${shift.area}` : ""}
          </Typography>
        </Card.Body>
        {(chip || shift.concern) && (
          <Card.Footer className="flex-row gap-2">
            {chip && (
              <Chip variant="soft" color={chip.color} size="sm">
                <Chip.Label>{chip.label}</Chip.Label>
              </Chip>
            )}
            {shift.concern && (
              <Chip variant="soft" color="warning" size="sm">
                <Chip.Label>Concern flagged</Chip.Label>
              </Chip>
            )}
          </Card.Footer>
        )}
      </Card>
    </Pressable>
  );
}
