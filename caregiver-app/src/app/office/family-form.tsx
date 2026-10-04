import { router, useLocalSearchParams } from "expo-router";
import { Button, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { CredentialsCard } from "@/components/CredentialsCard";
import {
  ActionCard,
  Field,
  SubmitButton,
  Toggle,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import type { ClientDetail, Credentials, FamilyLink } from "@/lib/api";
import { useOffice, useOfficeAction } from "@/lib/office";

/** Add a family member to a client (?client=), or edit one (&link=). */
export default function FamilyForm(): JSX.Element {
  const { client, link } = useLocalSearchParams<{ client: string; link?: string }>();
  const { data: c, reload } = useOffice<ClientDetail>(`/office/clients/${client}`);

  if (!c) {
    return (
      <Screen back="Client" title={link ? "Family member" : "Add family member"}>
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      </Screen>
    );
  }
  const existing = link ? (c.family.find((g) => String(g.id) === link) ?? null) : null;
  return <Form client={c} existing={existing} onChanged={reload} />;
}

function Form({
  client,
  existing,
  onChanged,
}: {
  client: ClientDetail;
  existing: FamilyLink | null;
  onChanged: () => void;
}) {
  const [v, setV] = useState({
    name: existing?.name ?? "",
    email: existing?.email ?? "",
    phone: existing?.phone ?? "",
    relationship: existing?.relationship ?? "",
    is_primary: existing?.is_primary ?? client.family.length === 0,
    is_bill_payer: existing?.is_bill_payer ?? false,
    // Notes describe personal care: off unless someone decides otherwise.
    can_view_notes: existing?.can_view_notes ?? false,
    can_view_invoices: existing?.can_view_invoices ?? false,
    can_request_changes: existing?.can_request_changes ?? true,
  });
  const set =
    <K extends keyof typeof v>(k: K) =>
    (value: (typeof v)[K]) =>
      setV((s) => ({ ...s, [k]: value }));

  const [creds, setCreds] = useState<Credentials | null>(null);
  const [removing, setRemoving] = useState(false);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () => {
    const body = { ...v, phone: v.phone || null, relationship: v.relationship || null };
    const outcome = existing
      ? await run(`/office/family/${existing.id}`, body, "PUT")
      : await run(`/office/clients/${client.id}/family`, body);
    setErrors(handle(outcome, () => router.back()));
  };

  const issue = async () => {
    if (!existing) return;
    handle(
      await run<{ credentials: Credentials }>(
        `/office/users/${existing.user_id}/temporary-password`
      ),
      (d) => {
        setCreds(d.credentials);
        onChanged();
      }
    );
  };

  const remove = async () => {
    if (!existing) return;
    handle(await run(`/office/family/${existing.id}`, {}, "DELETE"), () => router.back());
  };

  return (
    <Screen
      back={client.name}
      title={existing ? (existing.name ?? "Family member") : "Add family member"}
      subtitle={`For ${client.name}`}
    >
      {creds && <CredentialsCard creds={creds} onDone={() => setCreds(null)} />}

      <Section title="Person and login">
        <Field
          label="Full name"
          value={v.name}
          onChange={set("name")}
          error={first("name")}
          required
        />
        <Field
          label="Relationship"
          value={v.relationship}
          onChange={set("relationship")}
          placeholder="e.g. Daughter, Son, Nephew"
        />
        <Field
          label="Email"
          value={v.email}
          onChange={set("email")}
          keyboardType="email-address"
          autoCapitalize="none"
          error={first("email")}
          hint={
            existing
              ? "Their sign-in. Changing it changes it for every client they are linked to."
              : "Their sign-in. If they already have a family login for another client, use the same email."
          }
          required
        />
        <Field
          label="Phone / WhatsApp"
          value={v.phone}
          onChange={set("phone")}
          keyboardType="phone-pad"
        />
      </Section>

      <Section title="What they can see and do">
        <Toggle
          label="Main contact"
          hint="Called first. One per client; switching this on moves it from whoever had it."
          value={v.is_primary}
          onChange={set("is_primary")}
        />
        <Toggle
          label="Pays the bills"
          hint="Invoices are addressed to them. Usually also needs to see invoices."
          value={v.is_bill_payer}
          onChange={set("is_bill_payer")}
        />
        <Toggle
          label="Can read visit notes"
          hint="Notes describe personal care in detail. Off unless the client, or whoever holds their consent, agrees."
          value={v.can_view_notes}
          onChange={set("can_view_notes")}
        />
        <Toggle
          label="Can see invoices"
          hint="Amounts charged and payments made."
          value={v.can_view_invoices}
          onChange={set("can_view_invoices")}
        />
        <Toggle
          label="Can request care plan changes"
          hint="Requests reach the office; they do not change the plan by themselves."
          value={v.can_request_changes}
          onChange={set("can_request_changes")}
        />
      </Section>

      <SubmitButton
        label={existing ? "Save changes" : "Add family member"}
        busy={busy}
        onPress={save}
      />

      {existing && (
        <>
          <Section title="Sign-in">
            <Typography type="body-sm" color="muted">
              {existing.login_status === "invited"
                ? "Not set up yet. Issue a temporary password to get them started."
                : existing.login_status === "suspended"
                  ? "Sign-in suspended."
                  : "Active. Use this if they have forgotten their password."}
            </Typography>
            <Button variant="secondary" onPress={issue} isDisabled={busy}>
              {existing.login_status === "invited"
                ? "Set up sign-in"
                : "Issue a temporary password"}
            </Button>
          </Section>

          {removing ? (
            <ActionCard title="Remove from this client?" onClose={() => setRemoving(false)}>
              <Typography type="body-sm" color="muted">
                They lose access to {client.name} straight away. If this is their only client, their
                sign-in is suspended as well.
              </Typography>
              <SubmitButton label="Remove access" busy={busy} onPress={remove} danger />
            </ActionCard>
          ) : (
            <Button variant="danger-soft" onPress={() => setRemoving(true)}>
              Remove from this client
            </Button>
          )}
        </>
      )}
    </Screen>
  );
}
