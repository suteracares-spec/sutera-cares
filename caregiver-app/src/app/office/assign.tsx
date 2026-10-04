import { router, useLocalSearchParams } from "expo-router";
import { Alert, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import {
  ChoiceList,
  DateField,
  Field,
  SubmitButton,
  TimeField,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import type { AssignmentDetail, PlacementOptions } from "@/lib/api";
import { malaysiaDate } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/**
 * Assign a caregiver to a client (?client=), or with ?visit=1 book a single
 * visit such as a massage in one step.
 */
export default function Assign(): JSX.Element {
  const { client, name, visit } = useLocalSearchParams<{ client: string; name?: string; visit?: string }>();
  const oneOff = visit === "1";
  const { data: opts, error } = useOffice<PlacementOptions>(`/office/placement-options?client=${client}`);

  const today = malaysiaDate();
  const [v, setV] = useState({
    caregiver_id: null as number | null,
    service_id: null as number | null,
    role: "primary" as "primary" | "relief",
    status: "active" as "proposed" | "active",
    start_date: today,
    end_date: "",
    date: today,
    start_time: "",
    end_time: "",
    charge_rate: "",
    notes: "",
  });
  const set = <K extends keyof typeof v>(k: K) => (value: (typeof v)[K]) =>
    setV((s) => ({ ...s, [k]: value }));

  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const services = opts?.services.filter((s) => !oneOff || !s.needs_care_plan) ?? [];
  const service = opts?.services.find((s) => s.id === v.service_id);

  const save = async () => {
    const body = oneOff
      ? { one_off: true, caregiver_id: v.caregiver_id, service_id: v.service_id, date: v.date,
          start_time: v.start_time, end_time: v.end_time, charge_rate: v.charge_rate || null, notes: v.notes || null }
      : { caregiver_id: v.caregiver_id, service_id: v.service_id, role: v.role, status: v.status,
          start_date: v.start_date, end_date: v.end_date || null, charge_rate: v.charge_rate || null, notes: v.notes || null };
    setErrors(
      handle(await run<AssignmentDetail>(`/office/clients/${client}/assignments`, body), (a) =>
        router.replace({ pathname: "/office/assignment/[id]", params: { id: String(a.id) } })
      )
    );
  };

  return (
    <Screen back="Client" title={oneOff ? "Book a visit" : "Assign a caregiver"} subtitle={name}>
      {error && <ErrorBanner message={error} />}
      {!opts && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {opts && (
        <>
          {opts.caregivers.length === 0 && (
            <Alert status="warning">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Description>
                  No caregiver is placeable right now. Verify right to work and a current police check first.
                </Alert.Description>
              </Alert.Content>
            </Alert>
          )}

          <Section title="Service">
            <ChoiceList
              value={v.service_id}
              onChange={(id) => {
                const s = opts.services.find((x) => x.id === id);
                setV((st) => ({ ...st, service_id: id, charge_rate: s ? s.base_rate.toFixed(2) : st.charge_rate }));
              }}
              options={services.map((s) => ({
                value: s.id,
                label: s.name,
                hint: `RM ${s.base_rate.toFixed(2)} per ${s.unit}${s.needs_care_plan && !opts.client_has_care_plan ? " · needs an active care plan" : ""}`,
              }))}
            />
            {first("service_id") && <Typography type="body-sm" className="text-danger">{first("service_id")}</Typography>}
          </Section>

          <Section title="Caregiver">
            <ChoiceList
              value={v.caregiver_id}
              onChange={set("caregiver_id")}
              options={opts.caregivers.map((c) => ({
                value: c.id,
                label: c.name ?? c.code,
                hint: [c.code, c.area, c.gender, c.languages].filter(Boolean).join(" · "),
              }))}
            />
            {first("caregiver_id") && <Typography type="body-sm" className="text-danger">{first("caregiver_id")}</Typography>}
          </Section>

          {oneOff ? (
            <Section title="When">
              <DateField label="Date" value={v.date} onChange={set("date")} error={first("date")} />
              <View className="flex-row gap-3">
                <TimeField label="Starts" value={v.start_time} onChange={set("start_time")} error={first("start_time")} />
                <TimeField label="Ends" value={v.end_time} onChange={set("end_time")} error={first("end_time")} />
              </View>
            </Section>
          ) : (
            <Section title="Placement">
              <ChoiceList
                value={v.role}
                onChange={set("role")}
                options={[
                  { value: "primary" as const, label: "Primary", hint: "Their regular caregiver" },
                  { value: "relief" as const, label: "Relief", hint: "Covers when the primary is away" },
                ]}
              />
              <DateField label="Starts" value={v.start_date} onChange={set("start_date")} error={first("start_date")} />
              <DateField label="Ends (optional)" value={v.end_date} onChange={set("end_date")} error={first("end_date")} hint="Leave empty for ongoing care." />
              <ChoiceList
                value={v.status}
                onChange={set("status")}
                options={[
                  { value: "active" as const, label: "Confirmed", hint: "Agreed with the client and caregiver" },
                  { value: "proposed" as const, label: "Proposed", hint: "Still being agreed" },
                ]}
              />
            </Section>
          )}

          <Section title="Charge">
            <Field
              label={`Charge rate (RM${service ? ` per ${service.unit}` : ""})`}
              value={v.charge_rate}
              onChange={set("charge_rate")}
              keyboardType="number-pad"
              hint="Defaults to the service's standard rate."
              error={first("charge_rate")}
            />
            <Field label="Notes" value={v.notes} onChange={set("notes")} multiline error={first("notes")} />
          </Section>

          <SubmitButton
            label={oneOff ? "Book visit" : "Create assignment"}
            busy={busy}
            onPress={save}
          />
        </>
      )}
    </Screen>
  );
}
