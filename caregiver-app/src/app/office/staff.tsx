import { UserPlus } from "lucide-react-native";
import { Button, Card, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { CredentialsCard } from "@/components/CredentialsCard";
import { ActionCard, ChoiceList, Field, SubmitButton, useFieldErrors, useOutcome } from "@/components/ui/Form";
import { Initials, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import type { Credentials, StaffAccount } from "@/lib/api";
import { ago } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/**
 * Office accounts, administrators only. A leaver is suspended, never
 * deleted: their name stays on what they did.
 */
export default function Staff(): JSX.Element {
  const { data, error, loading, reload } = useOffice<{ staff: StaffAccount[] }>("/office/accounts");
  const [adding, setAdding] = useState(false);
  const [creds, setCreds] = useState<Credentials | null>(null);
  const accentFg = useThemeColor("accent-foreground");

  return (
    <Screen
      back="More"
      title="Staff"
      subtitle="Administrators and coordinators"
      action={
        !adding ? (
          <Button size="sm" onPress={() => setAdding(true)}>
            <UserPlus size={16} color={accentFg} />
            <Button.Label>Add</Button.Label>
          </Button>
        ) : undefined
      }
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      {creds && <CredentialsCard creds={creds} onDone={() => setCreds(null)} />}
      {adding && (
        <AddForm
          onClose={() => setAdding(false)}
          onDone={(c) => {
            setAdding(false);
            setCreds(c);
            reload();
          }}
        />
      )}

      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!data && loading && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {data && (
        <View className="gap-3">
          {data.staff.map((s) => (
            <StaffCard key={s.id} s={s} onChanged={reload} />
          ))}
        </View>
      )}

      <Typography type="body-xs" color="muted">
        Administrators also see the audit log and manage these accounts. Coordinators run the day.
      </Typography>
    </Screen>
  );
}

function StaffCard({ s, onChanged }: { s: StaffAccount; onChanged: () => void }) {
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const [confirm, setConfirm] = useState<"status" | "2fa" | null>(null);
  const suspended = s.status === "suspended";

  const act = async (path: string) =>
    handle(await run(path), () => {
      setConfirm(null);
      onChanged();
    });

  return (
    <Card className={suspended ? "opacity-70" : undefined}>
      <Card.Body className="gap-3">
        <View className="flex-row items-center gap-3">
          <Initials name={s.name} />
          <View className="flex-1">
            <Typography weight="semibold">
              {s.name}
              {s.is_me ? " (you)" : ""}
            </Typography>
            <Typography type="body-sm" color="muted">
              {s.email}
            </Typography>
            <Typography type="body-xs" color="muted">
              {s.role === "admin" ? "Administrator" : "Coordinator"}
              {s.last_login_at ? ` · signed in ${ago(s.last_login_at)}` : ""}
              {s.two_factor ? " · two-factor on" : ""}
            </Typography>
          </View>
          {s.status !== "active" && <StatusChip status={s.status} />}
        </View>

        {!s.is_me && confirm === null && (
          <View className="flex-row gap-2">
            <Button
              size="sm"
              className="flex-1"
              variant={suspended ? "secondary" : "danger-soft"}
              onPress={() => setConfirm("status")}
            >
              {suspended ? "Restore" : "Suspend"}
            </Button>
            {s.two_factor && (
              <Button size="sm" className="flex-1" variant="secondary" onPress={() => setConfirm("2fa")}>
                Reset two-factor
              </Button>
            )}
          </View>
        )}

        {confirm === "status" && (
          <ActionCard title={suspended ? `Restore ${s.name}?` : `Suspend ${s.name}?`} onClose={() => setConfirm(null)}>
            <Typography type="body-sm" color="muted">
              {suspended
                ? "They can sign in again with their existing password."
                : "They are signed out everywhere at once, the app included, and cannot sign in until restored."}
            </Typography>
            <SubmitButton
              label={suspended ? "Restore" : "Suspend"}
              danger={!suspended}
              busy={busy}
              onPress={() => act(`/office/accounts/${s.id}/status`)}
            />
          </ActionCard>
        )}
        {confirm === "2fa" && (
          <ActionCard title="Reset two-factor?" onClose={() => setConfirm(null)}>
            <Typography type="body-sm" color="muted">
              For a lost phone with no recovery codes left. They are signed out, and set up two-factor again
              when they next sign in on the website.
            </Typography>
            <SubmitButton label="Reset two-factor" busy={busy} onPress={() => act(`/office/accounts/${s.id}/reset-two-factor`)} />
          </ActionCard>
        )}
      </Card.Body>
    </Card>
  );
}

function AddForm({ onClose, onDone }: { onClose: () => void; onDone: (c: Credentials) => void }) {
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [role, setRole] = useState<"coordinator" | "admin">("coordinator");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () =>
    setErrors(
      handle(await run<{ credentials: Credentials }>("/office/accounts", { name, email, phone: phone || null, role }), (d) =>
        onDone(d.credentials)
      )
    );

  return (
    <ActionCard title="Add a staff account" onClose={onClose}>
      <Field label="Full name" value={name} onChange={setName} error={first("name")} required />
      <Field
        label="Email"
        value={email}
        onChange={setEmail}
        keyboardType="email-address"
        autoCapitalize="none"
        error={first("email")}
        required
      />
      <Field label="Phone" value={phone} onChange={setPhone} keyboardType="phone-pad" error={first("phone")} />
      <Section title="Role">
        <ChoiceList
          value={role}
          onChange={setRole}
          options={[
            { value: "coordinator" as const, label: "Coordinator", hint: "Runs the day: clients, caregivers, schedule, billing" },
            { value: "admin" as const, label: "Administrator", hint: "Also staff accounts, the audit log and system settings" },
          ]}
        />
      </Section>
      <SubmitButton label="Create account" busy={busy} onPress={save} />
    </ActionCard>
  );
}
