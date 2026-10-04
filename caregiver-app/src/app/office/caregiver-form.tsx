import { router, useLocalSearchParams } from "expo-router";
import { Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import {
  ChoiceList,
  DateField,
  Field,
  SubmitButton,
  Toggle,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { humanise, type CaregiverDetail, type CaregiverStatus } from "@/lib/api";
import { useOffice, useOfficeAction } from "@/lib/office";

const STATUSES: CaregiverStatus[] = ["applicant", "vetting", "active", "inactive", "left"];

/** New caregiver, or edit one (?id=). Vetting is recorded here. */
export default function CaregiverForm(): JSX.Element {
  const { id } = useLocalSearchParams<{ id?: string }>();
  const { data: existing } = useOffice<CaregiverDetail>(id ? `/office/caregivers/${id}` : null);

  if (id && !existing) {
    return (
      <Screen back="Back" title="Edit caregiver">
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      </Screen>
    );
  }
  return <Form existing={existing ?? null} />;
}

function Form({ existing }: { existing: CaregiverDetail | null }) {
  const [v, setV] = useState({
    name: existing?.name ?? "",
    email: existing?.email ?? "",
    phone: existing?.phone ?? "",
    ic_number: existing?.ic_number ?? "",
    gender: existing?.gender ?? null,
    dob: existing?.dob ?? "",
    base_area: existing?.base_area ?? "",
    max_travel_km: existing?.max_travel_km != null ? String(existing.max_travel_km) : "",
    languages: existing?.languages ?? "",
    hourly_rate: existing?.hourly_rate != null ? existing.hourly_rate.toFixed(2) : "",
    skills: existing?.skills ?? "",
    has_own_transport: existing?.has_own_transport ?? false,
    police_check_expires_at: existing?.police_check_expires_at ?? "",
    right_to_work_verified: existing?.right_to_work_verified ?? false,
    status: (existing?.status ?? "applicant") as CaregiverStatus,
  });
  const set = <K extends keyof typeof v>(k: K) => (value: (typeof v)[K]) =>
    setV((s) => ({ ...s, [k]: value }));

  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () => {
    const body = Object.fromEntries(Object.entries(v).map(([k, val]) => [k, val === "" ? null : val]));
    const outcome = existing
      ? await run<CaregiverDetail>(`/office/caregivers/${existing.id}`, body, "PUT")
      : await run<CaregiverDetail>("/office/caregivers", body);
    setErrors(
      handle(outcome, (c) =>
        existing
          ? router.back()
          : router.replace({ pathname: "/office/caregiver/[id]", params: { id: String(c.id) } })
      )
    );
  };

  return (
    <Screen back="Back" title={existing ? `Edit ${existing.code}` : "New caregiver"}>
      <Section title="Person and login">
        <Field label="Full name" value={v.name} onChange={set("name")} error={first("name")} required />
        <Field
          label="Email"
          value={v.email}
          onChange={set("email")}
          keyboardType="email-address"
          autoCapitalize="none"
          error={first("email")}
          hint="How they sign in to the app."
          required
        />
        <Field label="Phone / WhatsApp" value={v.phone} onChange={set("phone")} keyboardType="phone-pad" />
        <Field label="IC number" value={v.ic_number} onChange={set("ic_number")} hint="Encrypted at rest." />
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
        <Typography type="body-xs" color="muted">
          Some clients can only be assisted by a caregiver of the same gender.
        </Typography>
      </Section>

      <Section title="Matching">
        <View className="flex-row gap-3">
          <View className="flex-1">
            <Field label="Base area" value={v.base_area} onChange={set("base_area")} />
          </View>
          <View className="w-32">
            <Field label="Travels (km)" value={v.max_travel_km} onChange={set("max_travel_km")} keyboardType="number-pad" error={first("max_travel_km")} />
          </View>
        </View>
        <Field label="Languages spoken" value={v.languages} onChange={set("languages")} placeholder="e.g. Bahasa Malaysia, English, Tamil" />
        <Field
          label="Skills and experience"
          value={v.skills}
          onChange={set("skills")}
          multiline
          placeholder="e.g. dementia care, hoisting and transfers, post-natal, massage"
        />
        <Field
          label="Pay rate (RM / hour)"
          value={v.hourly_rate}
          onChange={set("hourly_rate")}
          keyboardType="number-pad"
          hint="What we pay them, not what the client is charged."
          error={first("hourly_rate")}
        />
        <Toggle label="Has own transport" value={v.has_own_transport} onChange={set("has_own_transport")} />
      </Section>

      <Section title="Vetting">
        <DateField
          label="Police check valid until"
          value={v.police_check_expires_at}
          onChange={set("police_check_expires_at")}
          hint="Flagged 60 days before it lapses."
        />
        <Toggle
          label="Right to work verified"
          hint="Not placeable until this is on and the police check is current."
          value={v.right_to_work_verified}
          onChange={set("right_to_work_verified")}
        />
        <Typography weight="semibold">Status</Typography>
        <ChoiceList
          value={v.status}
          onChange={set("status")}
          options={STATUSES.map((s) => ({
            value: s,
            label: humanise(s),
            hint: s === "inactive" || s === "left" ? "Suspends their sign-in" : null,
          }))}
        />
      </Section>

      <SubmitButton label={existing ? "Save changes" : "Create caregiver"} busy={busy} onPress={save} />
    </Screen>
  );
}
