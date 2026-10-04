import { router, useLocalSearchParams } from "expo-router";
import { Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import {
  ChoiceList,
  DateField,
  Field,
  SubmitButton,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { humanise, type ClientDetail, type ClientOptions, type ClientStatus } from "@/lib/api";
import { useOffice, useOfficeAction } from "@/lib/office";

/** New client, or edit one (?id=). Consent is recorded here. */
export default function ClientForm(): JSX.Element {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const { data: existing } = useOffice<ClientDetail>(id ? `/office/clients/${id}` : null);
  const { data: options } = useOffice<ClientOptions>("/office/client-options");

  if ((id && !existing) || !options) {
    return (
      <Screen back="Back" title={id ? "Edit client" : "New client"}>
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      </Screen>
    );
  }
  return <Form existing={existing ?? null} options={options} />;
}

function Form({ existing, options }: { existing: ClientDetail | null; options: ClientOptions }) {
  const [v, setV] = useState({
    name: existing?.name ?? "",
    status: (existing?.status ?? "assessment") as ClientStatus,
    ic_number: existing?.ic_number ?? "",
    dob: existing?.dob ?? "",
    gender: existing?.gender ?? null,
    address: existing?.address ?? "",
    area: existing?.area ?? "",
    postcode: existing?.postcode ?? "",
    mobility_level: existing?.mobility_level ?? null,
    languages: existing?.languages ?? "",
    allergies: existing?.allergies ?? "",
    notes: existing?.notes ?? "",
    consent_given_at: existing?.consent_given_at ?? "",
    consent_by: existing?.consent_by ?? "",
  });
  const set =
    <K extends keyof typeof v>(k: K) =>
    (value: (typeof v)[K]) =>
      setV((s) => ({ ...s, [k]: value }));

  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () => {
    // Empty boxes are "not recorded", not empty strings.
    const body = Object.fromEntries(
      Object.entries(v).map(([k, val]) => [k, val === "" ? null : val])
    );
    const outcome = existing
      ? await run<ClientDetail>(`/office/clients/${existing.id}`, body, "PUT")
      : await run<ClientDetail>("/office/clients", body);
    setErrors(
      handle(outcome, (client) =>
        existing
          ? router.back()
          : router.replace({ pathname: "/office/client/[id]", params: { id: String(client.id) } })
      )
    );
  };

  return (
    <Screen back="Back" title={existing ? `Edit ${existing.code}` : "New client"}>
      <Section title="Person">
        <Field
          label="Full name"
          value={v.name}
          onChange={set("name")}
          error={first("name")}
          required
        />
        <Field
          label="IC number"
          value={v.ic_number}
          onChange={set("ic_number")}
          error={first("ic_number")}
          hint="Encrypted at rest."
        />
        <DateField label="Date of birth" value={v.dob} onChange={set("dob")} error={first("dob")} />
        <Typography weight="semibold">Gender</Typography>
        <ChoiceList
          value={v.gender}
          onChange={set("gender")}
          options={[
            { value: null, label: "Not recorded" },
            { value: "female" as const, label: "Female" },
            { value: "male" as const, label: "Male" },
            { value: "other" as const, label: "Other" },
          ]}
        />
      </Section>

      <Section title="Where">
        <Field
          label="Address"
          value={v.address}
          onChange={set("address")}
          error={first("address")}
          multiline
        />
        <View className="flex-row gap-3">
          <View className="flex-1">
            <Field label="Area" value={v.area} onChange={set("area")} placeholder="e.g. Cheras" />
          </View>
          <View className="w-32">
            <Field
              label="Postcode"
              value={v.postcode}
              onChange={set("postcode")}
              keyboardType="number-pad"
            />
          </View>
        </View>
      </Section>

      <Section title="Care">
        <Typography weight="semibold">Mobility</Typography>
        <ChoiceList
          value={v.mobility_level}
          onChange={set("mobility_level")}
          options={[
            { value: null, label: "Not recorded" },
            ...options.mobility.map((m) => ({ value: m as string | null, label: humanise(m) })),
          ]}
        />
        <Field
          label="Languages spoken"
          value={v.languages}
          onChange={set("languages")}
          placeholder="e.g. Bahasa Malaysia, English"
        />
        <Field label="Allergies" value={v.allergies} onChange={set("allergies")} multiline />
        <Field
          label="Office notes"
          value={v.notes}
          onChange={set("notes")}
          multiline
          hint="Not shown to caregivers or family. Encrypted at rest."
        />
      </Section>

      <Section title="Consent (PDPA)">
        <DateField
          label="Consent recorded on"
          value={v.consent_given_at}
          onChange={set("consent_given_at")}
          error={first("consent_given_at")}
          hint="Explicit, dated consent is needed before care begins."
        />
        <Field
          label="Given by"
          value={v.consent_by}
          onChange={set("consent_by")}
          placeholder="The client, or e.g. Daughter (Nor Hayati)"
        />
      </Section>

      <Section title="Status">
        <ChoiceList
          value={v.status}
          onChange={set("status")}
          options={options.statuses.map((s) => ({ value: s, label: humanise(s) }))}
        />
      </Section>

      <SubmitButton
        label={existing ? "Save changes" : "Create client"}
        busy={busy}
        onPress={save}
      />
    </Screen>
  );
}
