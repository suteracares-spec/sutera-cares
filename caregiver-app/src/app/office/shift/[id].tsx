import { router, useLocalSearchParams } from "expo-router";
import { Alert, Button, Card, Chip, Separator, Spinner, Typography } from "heroui-native";
import type { JSX } from "react";
import { ScrollView, View } from "react-native";

import { SafeAreaView } from "@/components/SafeAreaView";
import type { OfficeShiftDetail } from "@/lib/api";
import { clock, shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** One shift, as the office sees it: who, when, and what the visit record says. */
export default function OfficeShiftScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: s, error } = useOffice<OfficeShiftDetail>(`/office/shifts/${id}`);

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top", "bottom"]}>
      <ScrollView contentContainerClassName="px-5 pb-12 gap-4">
        <Button variant="ghost" size="sm" className="self-start mt-2" onPress={() => router.back()}>
          ← Office
        </Button>

        {error && (
          <Alert status="danger">
            <Alert.Indicator />
            <Alert.Content>
              <Alert.Description>{error}</Alert.Description>
            </Alert.Content>
          </Alert>
        )}
        {!s && !error && (
          <View className="py-16 items-center">
            <Spinner size="lg" />
          </View>
        )}

        {s && (
          <>
            <View className="gap-1">
              <Typography.Heading type="h2">{s.client_details.name}</Typography.Heading>
              <Typography color="muted">
                {shortDay(s.date)} · {s.start}–{s.end}
                {s.service ? ` · ${s.service}` : ""}
              </Typography>
            </View>

            {s.attention && (
              <Alert status="danger">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Title>
                    {s.attention === "no_show" ? "Nobody checked in" : "Not checked in yet"}
                  </Alert.Title>
                  <Alert.Description>
                    Call the caregiver, or send relief cover from the website.
                  </Alert.Description>
                </Alert.Content>
              </Alert>
            )}
            {s.cancel_reason && (
              <Alert status="warning">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Title>{s.status === "missed" ? "Missed" : "Cancelled"}</Alert.Title>
                  <Alert.Description>{s.cancel_reason}</Alert.Description>
                </Alert.Content>
              </Alert>
            )}

            <Card>
              <Card.Body className="gap-3">
                <Fact
                  label="Caregiver"
                  value={`${s.caregiver ?? "Nobody assigned"}${s.covering ? " (covering)" : ""}`}
                />
                <Fact
                  label="Client"
                  value={`${s.client_details.code} · ${s.client_details.area ?? ""}`}
                />
                {s.client_details.address ? (
                  <Fact label="Address" value={s.client_details.address} />
                ) : null}
                {s.client_details.allergies ? (
                  <Fact label="Allergies" value={s.client_details.allergies} warn />
                ) : null}
              </Card.Body>
            </Card>

            <Card>
              <Card.Header>
                <Card.Title>Visit record</Card.Title>
              </Card.Header>
              <Card.Body className="gap-3">
                {!s.visit ? (
                  <Typography color="muted">Nobody has checked in.</Typography>
                ) : (
                  <>
                    <Fact
                      label="Checked in"
                      value={`${clock(s.visit.check_in_at)}${s.visit.location ? " (location recorded)" : ""}`}
                    />
                    <Fact
                      label="Checked out"
                      value={s.visit.check_out_at ? clock(s.visit.check_out_at) : "Not yet"}
                    />
                    {s.visit.minutes_worked ? (
                      <Fact
                        label="Time on site"
                        value={`${Math.floor(s.visit.minutes_worked / 60)}h ${s.visit.minutes_worked % 60}m`}
                      />
                    ) : null}
                    <Separator />
                    <Fact
                      label="Tasks done"
                      value={
                        s.visit.tasks_completed.length
                          ? s.visit.tasks_completed.map((t) => `✓ ${t}`).join("\n")
                          : "None recorded"
                      }
                    />
                    <Fact label="Visit note" value={s.visit.notes ?? "None"} />
                    {s.visit.concern_flagged && (
                      <View className="gap-1">
                        <Chip variant="soft" color="warning" size="sm" className="self-start">
                          <Chip.Label>Concern flagged</Chip.Label>
                        </Chip>
                        <Typography>{s.visit.concern_detail}</Typography>
                      </View>
                    )}
                  </>
                )}
              </Card.Body>
            </Card>

            <Typography type="body-xs" color="muted" align="center">
              To change this shift, use the portal on the website.
            </Typography>
          </>
        )}
      </ScrollView>
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
