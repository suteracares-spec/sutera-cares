import { router, useLocalSearchParams } from "expo-router";
import { Button, Card, Chip, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { OfficeShiftCard } from "@/components/OfficeShiftCard";
import {
  ActionCard,
  ChoiceList,
  DateField,
  Field,
  ReasonField,
  SubmitButton,
  TimeField,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { humanise, type AssignmentDetail } from "@/lib/api";
import { addDays, malaysiaDate, shortDay } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

type Panel = "pattern" | "single" | "edit" | "end" | null;

/** One placement: book its shifts, confirm it, change the rate, or end it. */
export default function AssignmentScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: a, error, loading, reload } = useOffice<AssignmentDetail>(`/office/assignments/${id}`);
  const [panel, setPanel] = useState<Panel>(null);

  const done = () => {
    setPanel(null);
    reload();
  };

  return (
    <Screen
      back="Back"
      title={a ? `${a.caregiver.name ?? a.caregiver.code} → ${a.client.name ?? a.client.code}` : undefined}
      subtitle={a ? `${a.service?.name ?? "Service"}${a.role === "relief" ? " · relief" : ""}` : undefined}
      refreshing={loading && !!a}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={error} />}
      {!a && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {a && (
        <>
          <Card>
            <Card.Body className="gap-2">
              <View className="flex-row gap-2">
                <StatusChip
                  status={a.status === "ended" ? "closed" : a.status === "proposed" ? "draft" : "active"}
                  label={a.status === "active" ? "Confirmed" : humanise(a.status)}
                />
                {a.one_off && (
                  <Chip size="sm" variant="secondary">
                    <Chip.Label>Single visit</Chip.Label>
                  </Chip>
                )}
              </View>
              <Typography>
                {a.one_off
                  ? shortDay(a.start_date!)
                  : `From ${a.start_date ? shortDay(a.start_date) : "?"}${a.end_date ? ` to ${shortDay(a.end_date)}` : ", ongoing"}`}
              </Typography>
              <Typography color="muted">
                {a.charge_rate != null ? `RM ${a.charge_rate.toFixed(2)} per ${a.service?.unit ?? "unit"}` : "No rate set"}
              </Typography>
              {a.notes ? <Typography type="body-sm" color="muted">{a.notes}</Typography> : null}
            </Card.Body>
          </Card>

          {a.status !== "ended" && panel === null && (
            <View className="gap-2">
              {!a.one_off && (
                <Button onPress={() => setPanel("pattern")}>Book a weekly pattern</Button>
              )}
              <Button variant="secondary" onPress={() => setPanel("single")}>
                Add a single shift
              </Button>
              <View className="flex-row gap-2">
                <Button className="flex-1" variant="secondary" onPress={() => setPanel("edit")}>
                  {a.status === "proposed" ? "Confirm or edit" : "Edit rate"}
                </Button>
                <Button className="flex-1" variant="danger-soft" onPress={() => setPanel("end")}>
                  End
                </Button>
              </View>
            </View>
          )}

          {panel === "pattern" && <PatternForm a={a} onClose={() => setPanel(null)} onDone={done} />}
          {panel === "single" && <SingleForm a={a} onClose={() => setPanel(null)} onDone={done} />}
          {panel === "edit" && <EditForm a={a} onClose={() => setPanel(null)} onDone={done} />}
          {panel === "end" && <EndForm a={a} onClose={() => setPanel(null)} onDone={done} />}

          <Section title={`Upcoming (${a.upcoming.length})`}>
            {a.upcoming.length === 0 ? (
              <Typography color="muted">Nothing booked ahead.</Typography>
            ) : (
              a.upcoming.map((s) => (
                <View key={s.id} className="gap-1">
                  <Typography type="body-xs" weight="bold" color="muted" className="uppercase tracking-wider">
                    {shortDay(s.date)}
                  </Typography>
                  <OfficeShiftCard shift={s} />
                </View>
              ))
            )}
          </Section>

          {a.recent.length > 0 && (
            <Section title="Recent">
              {a.recent.map((s) => (
                <View key={s.id} className="gap-1">
                  <Typography type="body-xs" weight="bold" color="muted" className="uppercase tracking-wider">
                    {shortDay(s.date)}
                  </Typography>
                  <OfficeShiftCard shift={s} />
                </View>
              ))}
            </Section>
          )}

          <Button
            variant="ghost"
            onPress={() => router.push({ pathname: "/office/client/[id]", params: { id: String(a.client.id) } })}
          >
            Open client
          </Button>
        </>
      )}
    </Screen>
  );
}

type FormProps = { a: AssignmentDetail; onClose: () => void; onDone: () => void };

function PatternForm({ a, onClose, onDone }: FormProps) {
  const today = malaysiaDate();
  const from0 = a.start_date && a.start_date > today ? a.start_date : today;
  const [days, setDays] = useState<number[]>([1, 2, 3, 4, 5]);
  const [start, setStart] = useState("08:00");
  const [end, setEnd] = useState("13:00");
  const [from, setFrom] = useState(from0);
  const [until, setUntil] = useState(a.end_date && a.end_date < addDays(from0, 27) ? a.end_date : addDays(from0, 27));
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const toggle = (d: number) => setDays((ds) => (ds.includes(d) ? ds.filter((x) => x !== d) : [...ds, d].sort()));

  const save = async () =>
    setErrors(
      handle(
        await run(`/office/assignments/${a.id}/shifts/generate`, { weekdays: days, start_time: start, end_time: end, from, until }),
        onDone
      )
    );

  return (
    <ActionCard title="Book a weekly pattern" onClose={onClose}>
      <View className="flex-row flex-wrap gap-2">
        {WEEKDAYS.map((w, i) => (
          <Chip key={w} variant={days.includes(i + 1) ? "primary" : "secondary"} onPress={() => toggle(i + 1)}>
            <Chip.Label>{w}</Chip.Label>
          </Chip>
        ))}
      </View>
      {first("weekdays") && <Typography type="body-sm" className="text-danger">{first("weekdays")}</Typography>}
      <View className="flex-row gap-3">
        <TimeField label="Starts" value={start} onChange={setStart} error={first("start_time")} />
        <TimeField label="Ends" value={end} onChange={setEnd} error={first("end_time")} />
      </View>
      <DateField label="From" value={from} onChange={setFrom} error={first("from")} />
      <DateField label="Until" value={until} onChange={setUntil} error={first("until")} hint="Up to 26 weeks at a time." />
      <Typography type="body-sm" color="muted">
        Days already booked are skipped. Days the caregiver is busy elsewhere are not booked and are listed, so you can arrange relief.
      </Typography>
      <SubmitButton label="Book shifts" busy={busy} onPress={save} />
    </ActionCard>
  );
}

function SingleForm({ a, onClose, onDone }: FormProps) {
  const [date, setDate] = useState(a.one_off && a.start_date ? a.start_date : malaysiaDate());
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () =>
    setErrors(
      handle(await run(`/office/assignments/${a.id}/shifts`, { shift_date: date, start_time: start, end_time: end }), onDone)
    );

  return (
    <ActionCard title="Add a single shift" onClose={onClose}>
      <DateField label="Day" value={date} onChange={setDate} error={first("shift_date")} />
      <View className="flex-row gap-3">
        <TimeField label="Starts" value={start} onChange={setStart} error={first("start_time")} />
        <TimeField label="Ends" value={end} onChange={setEnd} error={first("end_time")} />
      </View>
      <SubmitButton label="Book shift" busy={busy} onPress={save} />
    </ActionCard>
  );
}

function EditForm({ a, onClose, onDone }: FormProps) {
  const [status, setStatus] = useState<"proposed" | "active">(a.status === "proposed" ? "proposed" : "active");
  const [rate, setRate] = useState(a.charge_rate != null ? a.charge_rate.toFixed(2) : "");
  const [notes, setNotes] = useState(a.notes ?? "");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () =>
    setErrors(
      handle(
        await run(`/office/assignments/${a.id}`, { status, charge_rate: rate || null, notes: notes || null }, "PUT"),
        onDone
      )
    );

  return (
    <ActionCard title="Edit assignment" onClose={onClose}>
      <ChoiceList
        value={status}
        onChange={setStatus}
        options={[
          { value: "active" as const, label: "Confirmed" },
          { value: "proposed" as const, label: "Proposed" },
        ]}
      />
      <Field
        label={`Charge rate (RM per ${a.service?.unit ?? "unit"})`}
        value={rate}
        onChange={setRate}
        keyboardType="number-pad"
        error={first("charge_rate")}
        hint="Applies to invoices not yet issued."
      />
      <Field label="Notes" value={notes} onChange={setNotes} multiline error={first("notes")} />
      <SubmitButton label="Save" busy={busy} onPress={save} />
    </ActionCard>
  );
}

function EndForm({ a, onClose, onDone }: FormProps) {
  const [lastDay, setLastDay] = useState(malaysiaDate());
  const [reason, setReason] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () =>
    setErrors(handle(await run(`/office/assignments/${a.id}/end`, { last_day: lastDay, reason }), onDone));

  return (
    <ActionCard title="End this assignment" onClose={onClose}>
      <DateField label="Last day" value={lastDay} onChange={setLastDay} error={first("last_day")} />
      <ReasonField label="Reason" value={reason} onChange={setReason} error={first("reason")} placeholder="e.g. Client moved to a care home" />
      <Typography type="body-sm" color="muted">
        Shifts booked after the last day are cancelled. Past visits stay on record.
      </Typography>
      <SubmitButton label="End assignment" busy={busy} onPress={save} danger />
    </ActionCard>
  );
}
