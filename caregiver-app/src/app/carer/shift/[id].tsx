import * as Location from "expo-location";
import { router, useLocalSearchParams } from "expo-router";
import {
  Alert,
  Button,
  Card,
  Checkbox,
  Chip,
  ControlField,
  Description,
  Label,
  Separator,
  Spinner,
  TextArea,
  TextField,
  Typography,
  useThemeColor,
} from "heroui-native";
import { useEffect, useState, type JSX } from "react";
import { KeyboardAvoidingView, Linking, Platform, ScrollView, View } from "react-native";
import { SafeAreaView } from "@/components/SafeAreaView";

import { BackButton } from "@/components/ui/BackButton";
import { StatusChip } from "@/components/ui/Status";
import { clock, shortDay } from "@/lib/format";
import { useVisits } from "@/lib/visits";

const CONCERNS: [string, string][] = [
  ["safety", "Safety or a fall"],
  ["care_quality", "Quality of care"],
  ["attendance", "Lateness or a missed visit"],
  ["other", "Something else"],
];

/** Try for a location for a few seconds; check in without one rather than wait. */
async function locate(): Promise<{ lat?: number; lng?: number }> {
  try {
    const { status } = await Location.requestForegroundPermissionsAsync();
    if (status !== "granted") return {};
    const pos = await Promise.race([
      Location.getCurrentPositionAsync({ accuracy: Location.Accuracy.Balanced }),
      new Promise<null>((resolve) => setTimeout(() => resolve(null), 6000)),
    ]);
    return pos ? { lat: pos.coords.latitude, lng: pos.coords.longitude } : {};
  } catch {
    return {};
  }
}

export default function ShiftScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const shiftId = Number(id);
  const { detail, loadDetail, checkIn, checkOut, pendingFor } = useVisits();
  const shift = detail(shiftId);
  const waiting = pendingFor(shiftId).length > 0;
  const accentForeground = useThemeColor("accent-foreground");

  const [busy, setBusy] = useState(false);
  const [tasks, setTasks] = useState<number[]>([]);
  const [notes, setNotes] = useState("");
  const [flagged, setFlagged] = useState(false);
  const [category, setCategory] = useState("safety");
  const [concern, setConcern] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadDetail(shiftId);
  }, [shiftId, loadDetail]);

  if (!shift) {
    return (
      <SafeAreaView className="flex-1 bg-background items-center justify-center gap-4 px-6">
        <Spinner size="lg" />
        <Typography color="muted" align="center">
          Loading the shift. With no signal, open it once while online to keep a copy on this phone.
        </Typography>
        <Button variant="ghost" onPress={() => router.back()}>
          Back
        </Button>
      </SafeAreaView>
    );
  }

  const details = shift.client_details;
  const plan = shift.plan;

  const doCheckIn = async () => {
    setBusy(true);
    const where = await locate();
    await checkIn(shiftId, where.lat, where.lng);
    setBusy(false);
  };

  const doCheckOut = async () => {
    if (!notes.trim())
      return setError(
        "Write a short visit note: how they were, anything the family or the next caregiver should know."
      );
    if (flagged && !concern.trim())
      return setError("Describe the concern so the office can act on it.");
    setError(null);
    setBusy(true);
    await checkOut(shiftId, {
      tasks,
      notes: notes.trim(),
      concern_flagged: flagged,
      ...(flagged ? { concern_category: category, concern_detail: concern.trim() } : {}),
    });
    setBusy(false);
    router.back();
  };

  const toggle = (taskId: number, on: boolean) =>
    setTasks((t) => (on ? [...t, taskId] : t.filter((x) => x !== taskId)));

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top", "bottom"]}>
      <KeyboardAvoidingView
        className="flex-1"
        behavior={Platform.OS === "ios" ? "padding" : "height"}
      >
        <ScrollView
          contentContainerClassName="px-5 pb-12 gap-4"
          keyboardShouldPersistTaps="handled"
        >
          <View className="pt-2">
            <BackButton label="My shifts" />
          </View>

          <View className="gap-1">
            <Typography.Heading type="h2">{shift.client.name}</Typography.Heading>
            <Typography color="muted">
              {shortDay(shift.date)} · {shift.start}–{shift.end}
              {shift.service ? ` · ${shift.service}` : ""}
            </Typography>
            <View className="flex-row gap-2 mt-1">
              <StatusChip status={shift.status} />
              {waiting && (
                <Chip variant="soft" color="warning" size="sm">
                  <Chip.Label>Waiting to send</Chip.Label>
                </Chip>
              )}
            </View>
          </View>

          {/* ---- Before: check in ---- */}
          {shift.status === "scheduled" &&
            (shift.check_in_problem ? (
              <Alert status="default">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Description>{shift.check_in_problem}</Alert.Description>
                </Alert.Content>
              </Alert>
            ) : (
              <View className="gap-2">
                <Button size="lg" onPress={doCheckIn} isDisabled={busy} className="h-16">
                  {busy ? (
                    <Spinner size="sm" color={accentForeground} />
                  ) : (
                    "I have arrived: check in"
                  )}
                </Button>
                <Typography type="body-xs" color="muted" align="center">
                  Your location is recorded if your phone allows it. Works without signal.
                </Typography>
              </View>
            ))}

          {shift.status === "cancelled" && (
            <Alert status="warning">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Title>This shift was cancelled</Alert.Title>
                {shift.cancel_reason ? (
                  <Alert.Description>{shift.cancel_reason}</Alert.Description>
                ) : null}
              </Alert.Content>
            </Alert>
          )}

          {shift.status === "completed" && (
            <Alert status="success">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Title>Visit recorded</Alert.Title>
                <Alert.Description>
                  Checked in {clock(shift.visit?.check_in_at)}, out{" "}
                  {clock(shift.visit?.check_out_at)}.
                </Alert.Description>
              </Alert.Content>
            </Alert>
          )}

          {/* ---- During: tasks, note, concern, check out ---- */}
          {shift.status === "in_progress" && (
            <>
              <Alert status="accent">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Title>Checked in at {clock(shift.visit?.check_in_at)}</Alert.Title>
                </Alert.Content>
              </Alert>

              {plan && plan.tasks.length > 0 && (
                <Card>
                  <Card.Header>
                    <Card.Title>Tasks</Card.Title>
                  </Card.Header>
                  <Card.Body className="gap-1">
                    {plan.tasks.map((t) => (
                      <ControlField
                        key={t.id}
                        isSelected={tasks.includes(t.id)}
                        onSelectedChange={(on: boolean) => toggle(t.id, on)}
                        className="py-2"
                      >
                        <ControlField.Indicator>
                          <Checkbox />
                        </ControlField.Indicator>
                        <View className="flex-1">
                          <Label>{t.description}</Label>
                          {t.when ? <Description>{t.when}</Description> : null}
                        </View>
                      </ControlField>
                    ))}
                  </Card.Body>
                </Card>
              )}

              <TextField isRequired isInvalid={!!error && !notes.trim()}>
                <Label>Visit note</Label>
                <TextArea
                  value={notes}
                  onChangeText={setNotes}
                  placeholder="How were they today? Eating, mood, anything new."
                  numberOfLines={5}
                  className="min-h-28"
                />
              </TextField>

              <ControlField isSelected={flagged} onSelectedChange={setFlagged}>
                <ControlField.Indicator>
                  <Checkbox />
                </ControlField.Indicator>
                <View className="flex-1">
                  <Label>I am worried about something</Label>
                  <Description>The office is told straight away.</Description>
                </View>
              </ControlField>

              {flagged && (
                <View className="gap-3">
                  <View className="flex-row flex-wrap gap-2">
                    {CONCERNS.map(([value, label]) => (
                      <Chip
                        key={value}
                        variant={category === value ? "primary" : "secondary"}
                        color={category === value ? "danger" : "default"}
                        onPress={() => setCategory(value)}
                      >
                        <Chip.Label>{label}</Chip.Label>
                      </Chip>
                    ))}
                  </View>
                  <TextField isRequired>
                    <Label>What happened?</Label>
                    <TextArea
                      value={concern}
                      onChangeText={setConcern}
                      maxLength={600}
                      placeholder="If anyone is in danger, call 999 first."
                    />
                  </TextField>
                </View>
              )}

              {error && (
                <Alert status="danger">
                  <Alert.Indicator />
                  <Alert.Content>
                    <Alert.Description>{error}</Alert.Description>
                  </Alert.Content>
                </Alert>
              )}

              <Button size="lg" onPress={doCheckOut} isDisabled={busy} className="h-16">
                {busy ? <Spinner size="sm" color={accentForeground} /> : "Check out and save"}
              </Button>
            </>
          )}

          {/* ---- What to know about this person ---- */}
          {details && (
            <Card>
              <Card.Header>
                <Card.Title>About the client</Card.Title>
              </Card.Header>
              <Card.Body className="gap-3">
                <Fact
                  label="Address"
                  value={
                    [details.address, details.postcode].filter(Boolean).join(" ") ||
                    "Ask the office"
                  }
                />
                {details.address ? (
                  <Button
                    size="sm"
                    variant="outline"
                    className="self-start"
                    onPress={() =>
                      Linking.openURL(
                        `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${details.address} ${details.postcode ?? ""}`)}`
                      )
                    }
                  >
                    Open in Maps
                  </Button>
                ) : null}
                <Separator />
                <Fact label="Mobility" value={details.mobility ?? "Not recorded"} />
                <Fact label="Languages" value={details.languages ?? "Not recorded"} />
                <Fact
                  label="Allergies"
                  value={details.allergies ?? "None recorded"}
                  warn={!!details.allergies}
                />
              </Card.Body>
            </Card>
          )}

          {plan?.notes ? (
            <Card>
              <Card.Header>
                <Card.Title>How they like things done</Card.Title>
              </Card.Header>
              <Card.Body>
                <Typography>{plan.notes}</Typography>
              </Card.Body>
            </Card>
          ) : null}

          {shift.status !== "in_progress" && plan && plan.tasks.length > 0 && (
            <Card>
              <Card.Header>
                <Card.Title>Care plan</Card.Title>
              </Card.Header>
              <Card.Body className="gap-1">
                {plan.tasks.map((t) => (
                  <Typography key={t.id}>• {t.description}</Typography>
                ))}
              </Card.Body>
            </Card>
          )}

          <Typography type="body-xs" color="muted" align="center" className="mt-2">
            Caregivers do not give injections, dress wounds, manage IV lines or catheters, or change
            doses. If someone needs that, call the office.
          </Typography>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

function Fact({ label, value, warn }: { label: string; value: string; warn?: boolean }) {
  return (
    <View>
      <Typography type="body-xs" weight="bold" color="muted" className="uppercase tracking-wider">
        {label}
      </Typography>
      <Typography className={warn ? "text-danger font-semibold" : undefined}>{value}</Typography>
    </View>
  );
}
