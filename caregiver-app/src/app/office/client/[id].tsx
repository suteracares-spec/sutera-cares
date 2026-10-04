import { router, useLocalSearchParams } from "expo-router";
import {
  ClipboardList,
  ClipboardPlus,
  KeyRound,
  Pencil,
  ShieldAlert,
  UserPlus,
  UserRound,
} from "lucide-react-native";
import { Alert, Button, Card, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { CredentialsCard } from "@/components/CredentialsCard";
import { ActionCard, Field, SubmitButton, useFieldErrors, useOutcome } from "@/components/ui/Form";
import { EmptyState, ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { humanise, type CarePlanDetail, type ClientDetail, type Credentials } from "@/lib/api";
import { shortDay } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/** A client's whole record: details, consent, care plan, family, caregivers, sign-in. */
export default function ClientScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: c, error, loading, reload } = useOffice<ClientDetail>(`/office/clients/${id}`);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const [creds, setCreds] = useState<Credentials | null>(null);
  const [archiving, setArchiving] = useState(false);
  const [loginEmail, setLoginEmail] = useState("");
  const [addingLogin, setAddingLogin] = useState(false);
  const { setErrors, first } = useFieldErrors();
  const [accent, danger] = useThemeColor(["accent", "danger"]);

  const activePlan = c?.care_plans.find((p) => p.status === "active");
  const draftPlan = c?.care_plans.find((p) => p.status === "draft");
  const history = c?.care_plans.filter((p) => p.status === "superseded") ?? [];

  const openPlan = (planId: number) =>
    router.push({ pathname: "/office/care-plan/[id]", params: { id: String(planId) } });

  const startPlan = async () => {
    if (!c) return;
    handle(await run<CarePlanDetail>(`/office/clients/${c.id}/care-plans`), (plan) =>
      openPlan(plan.id)
    );
  };

  const issuePassword = async (userId: number) =>
    handle(
      await run<{ credentials: Credentials }>(`/office/users/${userId}/temporary-password`),
      (d) => {
        setCreds(d.credentials);
        reload();
      }
    );

  const createLogin = async () => {
    if (!c) return;
    setErrors(
      handle(
        await run<{ credentials: Credentials }>(`/office/clients/${c.id}/sign-in`, {
          email: loginEmail,
        }),
        (d) => {
          setCreds(d.credentials);
          setAddingLogin(false);
          reload();
        }
      )
    );
  };

  const archive = async () => {
    if (!c) return;
    handle(await run(`/office/clients/${c.id}`, {}, "DELETE"), () => router.back());
  };

  return (
    <Screen
      back="Clients"
      title={c?.name}
      subtitle={
        c ? `${c.code}${c.area ? ` · ${c.area}` : ""}${c.age ? ` · ${c.age} yrs` : ""}` : undefined
      }
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
            <StatusChip status={c.status} />
          </View>

          {creds && <CredentialsCard creds={creds} onDone={() => setCreds(null)} />}

          {!c.consent_given_at && (
            <Alert status="danger">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Title>No consent recorded</Alert.Title>
                <Alert.Description>
                  Health information is sensitive personal data under the PDPA. Record explicit,
                  dated consent (Edit) before care begins.
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
                onPress={() =>
                  router.push({ pathname: "/office/client-form", params: { id: String(c.id) } })
                }
              >
                <Pencil size={14} color={accent} />
                <Button.Label>Edit</Button.Label>
              </Button>
            }
          >
            <Card>
              <Card.Body className="gap-3">
                <Fact label="Mobility" value={humanise(c.mobility_level) || "Not recorded"} />
                <Fact label="Languages" value={c.languages || "Not recorded"} />
                <Fact
                  label="Allergies"
                  value={c.allergies || "None recorded"}
                  warn={!!c.allergies}
                />
                <Fact
                  label="Address"
                  value={[c.address, c.postcode].filter(Boolean).join(" ") || "Not recorded"}
                />
                <Fact label="IC number" value={c.ic_number || "Not recorded"} />
                <Fact
                  label="Consent"
                  value={
                    c.consent_given_at
                      ? `${shortDay(c.consent_given_at)}, given by ${c.consent_by || "the client"}`
                      : "Not recorded"
                  }
                />
                {c.notes ? <Fact label="Office notes" value={c.notes} /> : null}
              </Card.Body>
            </Card>
          </Section>

          <Section title="Care plan">
            <ListCard>
              {activePlan && (
                <Row
                  icon={ClipboardList}
                  title={`Version ${activePlan.version} · ${activePlan.tasks} tasks`}
                  subtitle={`From ${activePlan.effective_from ? shortDay(activePlan.effective_from) : "?"}, agreed by ${activePlan.agreed_by ?? "?"}`}
                  trailing={<StatusChip status="active" label="Current" />}
                  onPress={() => openPlan(activePlan.id)}
                />
              )}
              {draftPlan && (
                <Row
                  icon={ClipboardList}
                  title={`Version ${draftPlan.version} · ${draftPlan.tasks} tasks`}
                  subtitle="Not yet agreed"
                  trailing={<StatusChip status="draft" />}
                  onPress={() => openPlan(draftPlan.id)}
                  last={history.length === 0}
                />
              )}
              {history.map((p, i) => (
                <Row
                  key={p.id}
                  icon={ClipboardList}
                  title={`Version ${p.version}`}
                  subtitle="History"
                  onPress={() => openPlan(p.id)}
                  last={!!draftPlan && i === history.length - 1}
                />
              ))}
              {!draftPlan && (
                <Row
                  icon={ClipboardPlus}
                  title={activePlan ? "Revise the care plan" : "Start a care plan"}
                  subtitle={
                    activePlan
                      ? "A new version, copied from the current one"
                      : "What the caregiver does on each visit"
                  }
                  onPress={busy ? undefined : startPlan}
                  last
                />
              )}
            </ListCard>
          </Section>

          <Section
            title="Family access"
            action={
              <Button
                size="sm"
                variant="ghost"
                onPress={() =>
                  router.push({ pathname: "/office/family-form", params: { client: String(c.id) } })
                }
              >
                <UserPlus size={14} color={accent} />
                <Button.Label>Add</Button.Label>
              </Button>
            }
          >
            {c.family.length === 0 ? (
              <Card>
                <Card.Body>
                  <Typography color="muted">No family members linked.</Typography>
                </Card.Body>
              </Card>
            ) : (
              <ListCard>
                {c.family.map((g, i) => (
                  <Row
                    key={g.id}
                    initials={g.name ?? "?"}
                    title={`${g.name}${g.is_primary ? " · main contact" : ""}`}
                    subtitle={[
                      g.relationship,
                      g.can_view_notes ? "sees notes" : null,
                      g.can_view_invoices ? "sees invoices" : null,
                      g.login_status === "invited"
                        ? "not signed in yet"
                        : g.login_status === "suspended"
                          ? "suspended"
                          : null,
                    ]
                      .filter(Boolean)
                      .join(" · ")}
                    onPress={() =>
                      router.push({
                        pathname: "/office/family-form",
                        params: { client: String(c.id), link: String(g.id) },
                      })
                    }
                    last={i === c.family.length - 1}
                  />
                ))}
              </ListCard>
            )}
          </Section>

          <Section title="Caregivers and visits">
            {c.assignments.length === 0 ? (
              <EmptyState
                icon={UserRound}
                title="Nobody assigned yet"
                hint="Assigning caregivers comes to the app next; use the website for now."
              />
            ) : (
              <ListCard>
                {c.assignments.map((a, i) => (
                  <Row
                    key={a.id}
                    initials={a.caregiver ?? "?"}
                    title={a.caregiver ?? "Unknown"}
                    subtitle={`${a.service ?? "Service"} · from ${a.start_date ? shortDay(a.start_date) : "?"}${a.end_date ? ` to ${shortDay(a.end_date)}` : ""}`}
                    trailing={
                      <StatusChip
                        status={
                          a.status === "ended"
                            ? "closed"
                            : a.status === "proposed"
                              ? "draft"
                              : "active"
                        }
                        label={humanise(a.status === "active" ? "confirmed" : a.status)}
                      />
                    }
                    last={i === c.assignments.length - 1}
                  />
                ))}
              </ListCard>
            )}
          </Section>

          <Section title="Client's own sign-in">
            {c.login ? (
              <ListCard>
                <Row
                  icon={KeyRound}
                  title={c.login.email}
                  subtitle={
                    c.login.status === "invited" ? "Not signed in yet" : humanise(c.login.status)
                  }
                  trailing={
                    <Button
                      size="sm"
                      variant="secondary"
                      onPress={() => issuePassword(c.login!.id)}
                      isDisabled={busy}
                    >
                      New password
                    </Button>
                  }
                  last
                />
              </ListCard>
            ) : addingLogin ? (
              <ActionCard title="Give the client a sign-in" onClose={() => setAddingLogin(false)}>
                <Field
                  label="Client's email"
                  value={loginEmail}
                  onChange={setLoginEmail}
                  keyboardType="email-address"
                  autoCapitalize="none"
                  error={first("email")}
                  required
                />
                <SubmitButton label="Create sign-in" busy={busy} onPress={createLogin} />
              </ActionCard>
            ) : (
              <Button variant="secondary" onPress={() => setAddingLogin(true)}>
                Give the client their own sign-in
              </Button>
            )}
          </Section>

          {archiving ? (
            <ActionCard title={`Archive ${c.code}?`} onClose={() => setArchiving(false)}>
              <Typography type="body-sm" color="muted">
                Hides the client from lists but keeps the record. Visit history is evidence and is
                never destroyed by this.
              </Typography>
              <SubmitButton label="Archive client" busy={busy} onPress={archive} danger />
            </ActionCard>
          ) : (
            <Button variant="danger-soft" onPress={() => setArchiving(true)}>
              <ShieldAlert size={16} color={danger} />
              <Button.Label>Archive client</Button.Label>
            </Button>
          )}
        </>
      )}
    </Screen>
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
