import { router, useLocalSearchParams } from "expo-router";
import { Pencil, ShieldAlert } from "lucide-react-native";
import { Alert, Button, Card, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { CredentialsCard } from "@/components/CredentialsCard";
import { ActionCard, SubmitButton, useOutcome } from "@/components/ui/Form";
import { ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { humanise, type CaregiverDetail, type Credentials } from "@/lib/api";
import { shortDay } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/** A caregiver: vetting, matching details, placements and sign-in. */
export default function CaregiverScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: c, error, loading, reload } = useOffice<CaregiverDetail>(`/office/caregivers/${id}`);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const [creds, setCreds] = useState<Credentials | null>(null);
  const [archiving, setArchiving] = useState(false);
  const [accent, danger] = useThemeColor(["accent", "danger"]);

  const issue = async () => {
    if (!c) return;
    handle(await run<{ credentials: Credentials }>(`/office/users/${c.user_id}/temporary-password`), (d) => {
      setCreds(d.credentials);
      reload();
    });
  };

  const archive = async () => {
    if (!c) return;
    handle(await run(`/office/caregivers/${c.id}`, {}, "DELETE"), () => router.back());
  };

  const checkLapsed = c?.police_check_expires_at ? c.police_check_expires_at < new Date().toISOString().slice(0, 10) : false;

  return (
    <Screen
      back="Caregivers"
      title={c?.name ?? undefined}
      subtitle={c ? `${c.code}${c.area ? ` · ${c.area}` : ""}` : undefined}
      refreshing={loading && !!c}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={error} />}
      {!c && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {c && (
        <>
          <View className="flex-row gap-2">
            <StatusChip status={c.status === "active" ? "active" : "draft"} label={humanise(c.status)} />
            <StatusChip status={c.placeable ? "active" : "open"} label={c.placeable ? "Placeable" : "Not placeable"} />
          </View>

          {creds && <CredentialsCard creds={creds} onDone={() => setCreds(null)} />}

          {!c.placeable && c.status === "active" && (
            <Alert status="danger">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Title>Not placeable</Alert.Title>
                <Alert.Description>
                  {[
                    !c.right_to_work_verified ? "Right to work is not verified." : null,
                    !c.police_check_expires_at ? "No police check on file." : checkLapsed ? `Police check expired ${shortDay(c.police_check_expires_at)}.` : null,
                  ]
                    .filter(Boolean)
                    .join(" ")}
                </Alert.Description>
              </Alert.Content>
            </Alert>
          )}
          {c.check_expiring && !checkLapsed && (
            <Alert status="warning">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Description>
                  Police check lapses {shortDay(c.police_check_expires_at!)}. Renew it before then.
                </Alert.Description>
              </Alert.Content>
            </Alert>
          )}

          <Section
            title="Record"
            action={
              <Button
                size="sm"
                variant="ghost"
                onPress={() => router.push({ pathname: "/office/caregiver-form", params: { id: String(c.id) } })}
              >
                <Pencil size={14} color={accent} />
                <Button.Label>Edit</Button.Label>
              </Button>
            }
          >
            <Card>
              <Card.Body className="gap-3">
                <Fact label="Contact" value={[c.phone, c.email].filter(Boolean).join(" · ") || "Not recorded"} />
                <Fact label="Languages" value={c.languages || "Not recorded"} />
                <Fact label="Skills" value={c.skills || "Not recorded"} />
                <Fact
                  label="Travel"
                  value={`${c.has_own_transport ? "Own transport" : "No own transport"}${c.max_travel_km ? `, up to ${c.max_travel_km} km` : ""}`}
                />
                <Fact label="Pay rate" value={c.hourly_rate != null ? `RM ${c.hourly_rate.toFixed(2)} / hour` : "Not set"} />
                <Fact
                  label="Vetting"
                  value={`Right to work ${c.right_to_work_verified ? "verified" : "NOT verified"} · police check ${c.police_check_expires_at ? `until ${shortDay(c.police_check_expires_at)}` : "missing"}`}
                />
              </Card.Body>
            </Card>
          </Section>

          <Section title="Clients">
            {c.assignments.length === 0 ? (
              <Card>
                <Card.Body>
                  <Typography color="muted">No placements yet. Assign them from a client&apos;s page.</Typography>
                </Card.Body>
              </Card>
            ) : (
              <ListCard>
                {c.assignments.map((a, i) => (
                  <Row
                    key={a.id}
                    initials={a.client ?? "?"}
                    title={a.client ?? "Client"}
                    subtitle={`${a.service ?? "Service"}${a.role === "relief" ? " · relief" : ""} · from ${a.start_date ? shortDay(a.start_date) : "?"}`}
                    trailing={<StatusChip status={a.status === "ended" ? "closed" : a.status === "proposed" ? "draft" : "active"} label={a.status === "active" ? "Confirmed" : humanise(a.status)} />}
                    onPress={() => router.push({ pathname: "/office/assignment/[id]", params: { id: String(a.id) } })}
                    last={i === c.assignments.length - 1}
                  />
                ))}
              </ListCard>
            )}
          </Section>

          <Section title="Sign-in">
            <Typography type="body-sm" color="muted">
              {c.login_status === "invited"
                ? "Not set up yet. Issue a temporary password so they can use the app."
                : c.login_status === "suspended"
                  ? "Sign-in suspended."
                  : `Active${c.last_login_at ? `, last signed in ${shortDay(c.last_login_at.slice(0, 10))}` : ""}.`}
            </Typography>
            <Button variant="secondary" onPress={issue} isDisabled={busy}>
              {c.login_status === "invited" ? "Set up sign-in" : "Issue a temporary password"}
            </Button>
          </Section>

          {archiving ? (
            <ActionCard title={`Archive ${c.code}?`} onClose={() => setArchiving(false)}>
              <Typography type="body-sm" color="muted">
                Revokes their access immediately and marks them as left. Past visit records stay
                attached, because those are evidence.
              </Typography>
              <SubmitButton label="Archive caregiver" busy={busy} onPress={archive} danger />
            </ActionCard>
          ) : (
            <Button variant="danger-soft" onPress={() => setArchiving(true)}>
              <ShieldAlert size={16} color={danger} />
              <Button.Label>Archive caregiver</Button.Label>
            </Button>
          )}
        </>
      )}
    </Screen>
  );
}

function Fact({ label, value }: { label: string; value: string }) {
  return (
    <View>
      <Typography type="body-xs" weight="bold" color="muted" className="uppercase tracking-wider">
        {label}
      </Typography>
      <Typography>{value}</Typography>
    </View>
  );
}
