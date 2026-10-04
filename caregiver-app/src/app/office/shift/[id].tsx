import { useLocalSearchParams } from "expo-router";
import {
  ArrowRightLeft,
  CalendarClock,
  CircleSlash,
  ClipboardPen,
  UserRoundX,
} from "lucide-react-native";
import { Card, Chip, Separator, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import {
  ActionCard,
  ChoiceList,
  DayStepper,
  ReasonField,
  SubmitButton,
  TimeField,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import type { OfficeShiftDetail, ReliefOption } from "@/lib/api";
import { clock, shortDay } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

type Mode = null | "cover" | "move" | "cancel" | "missed" | "correct";

/** One shift, as the office sees it, and what can be done to it. */
export default function OfficeShiftScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: s, error, loading, reload } = useOffice<OfficeShiftDetail>(`/office/shifts/${id}`);
  const [mode, setMode] = useState<Mode>(null);

  // The server marks a booked shift whose end has passed with nobody checked in.
  const ended = s?.attention === "no_show";
  const done = () => {
    setMode(null);
    reload();
  };

  return (
    <Screen
      back="Back"
      title={s?.client_details.name}
      subtitle={
        s
          ? `${shortDay(s.date)} · ${s.start}–${s.end}${s.service ? ` · ${s.service}` : ""}`
          : undefined
      }
      refreshing={loading && !!s}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={error} />}
      {!s && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {s && (
        <>
          <View className="flex-row gap-2">
            <StatusChip status={s.attention ?? s.status} />
            {s.covering && (
              <Chip variant="soft" color="accent" size="sm">
                <Chip.Label>Relief cover</Chip.Label>
              </Chip>
            )}
          </View>

          {s.cancel_reason && (
            <Card>
              <Card.Body>
                <Typography weight="semibold">
                  {s.status === "missed" ? "Why it was missed" : "Why it was cancelled"}
                </Typography>
                <Typography>{s.cancel_reason}</Typography>
              </Card.Body>
            </Card>
          )}

          <Card>
            <Card.Body className="gap-3">
              <Fact
                label="Caregiver"
                value={`${s.caregiver ?? "Nobody assigned"}${s.covering ? " (covering)" : ""}`}
              />
              <Fact
                label="Client"
                value={`${s.client_details.code}${s.client_details.area ? ` · ${s.client_details.area}` : ""}`}
              />
              {s.client_details.address ? (
                <Fact label="Address" value={s.client_details.address} />
              ) : null}
              {s.client_details.allergies ? (
                <Fact label="Allergies" value={s.client_details.allergies} warn />
              ) : null}
            </Card.Body>
          </Card>

          <Section title="Visit record">
            <Card>
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
          </Section>

          {mode === null && s.status !== "cancelled" && (
            <Section title="Actions">
              <ListCard>
                {s.status === "scheduled" && (
                  <>
                    <Row
                      icon={ArrowRightLeft}
                      title="Send relief cover"
                      subtitle="Another caregiver, for this shift only"
                      onPress={() => setMode("cover")}
                    />
                    <Row
                      icon={CalendarClock}
                      title="Move"
                      subtitle="Another day or time"
                      onPress={() => setMode("move")}
                    />
                    {ended && (
                      <Row
                        icon={UserRoundX}
                        title="Mark as missed"
                        subtitle="Nobody came"
                        onPress={() => setMode("missed")}
                      />
                    )}
                  </>
                )}
                <Row
                  icon={ClipboardPen}
                  title={s.visit ? "Correct the visit record" : "Record the visit by hand"}
                  subtitle="Flat phone, forgotten check-out"
                  onPress={() => setMode("correct")}
                  last={s.status !== "scheduled"}
                />
                {s.status === "scheduled" && (
                  <Row
                    icon={CircleSlash}
                    title="Cancel shift"
                    danger
                    onPress={() => setMode("cancel")}
                    last
                  />
                )}
              </ListCard>
            </Section>
          )}

          {mode === "cover" && <CoverForm shift={s} onDone={done} onClose={() => setMode(null)} />}
          {mode === "move" && <MoveForm shift={s} onDone={done} onClose={() => setMode(null)} />}
          {mode === "cancel" && (
            <ReasonForm
              shift={s}
              title="Cancel this shift"
              path="cancel"
              field="cancel_reason"
              label="Why"
              placeholder="Family away; client in hospital; public holiday"
              button="Cancel shift"
              danger
              onDone={done}
              onClose={() => setMode(null)}
            />
          )}
          {mode === "missed" && (
            <ReasonForm
              shift={s}
              title="Mark as missed"
              path="missed"
              field="reason"
              label="What happened"
              placeholder="Caregiver ill, no relief found"
              button="Mark as missed"
              danger
              onDone={done}
              onClose={() => setMode(null)}
            />
          )}
          {mode === "correct" && (
            <CorrectForm shift={s} onDone={done} onClose={() => setMode(null)} />
          )}
        </>
      )}
    </Screen>
  );
}

type FormProps = { shift: OfficeShiftDetail; onDone: () => void; onClose: () => void };

function ReasonForm({
  shift,
  title,
  path,
  field,
  label,
  placeholder,
  button,
  danger,
  onDone,
  onClose,
}: FormProps & {
  title: string;
  path: string;
  field: string;
  label: string;
  placeholder: string;
  button: string;
  danger?: boolean;
}) {
  const [reason, setReason] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const submit = async () =>
    setErrors(handle(await run(`/office/shifts/${shift.id}/${path}`, { [field]: reason }), onDone));

  return (
    <ActionCard title={title} onClose={onClose}>
      <ReasonField
        label={label}
        value={reason}
        onChange={setReason}
        placeholder={placeholder}
        error={first(field)}
      />
      <SubmitButton label={button} busy={busy} onPress={submit} danger={danger} />
    </ActionCard>
  );
}

function CoverForm({ shift, onDone, onClose }: FormProps) {
  const { data } = useOffice<{ caregivers: ReliefOption[] }>(`/office/shifts/${shift.id}/relief`);
  const [choice, setChoice] = useState<number | null>(null);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const submit = async () =>
    setErrors(
      handle(await run(`/office/shifts/${shift.id}/cover`, { covered_by_id: choice }), onDone)
    );

  return (
    <ActionCard title="Send relief cover" onClose={onClose}>
      {!data ? (
        <Spinner />
      ) : (
        <ChoiceList
          value={choice}
          onChange={setChoice}
          options={[
            ...(shift.covering
              ? [{ value: null as number | null, label: "Back to the assigned caregiver" }]
              : []),
            ...data.caregivers.map((c) => ({
              value: c.id as number | null,
              label: c.name ?? c.code,
              hint: c.area,
            })),
          ]}
        />
      )}
      {first("covered_by_id") ? (
        <Typography className="text-danger">{first("covered_by_id")}</Typography>
      ) : null}
      <Typography type="body-sm" color="muted">
        Only placeable caregivers are listed. Anyone already booked at this time is refused.
      </Typography>
      <SubmitButton label="Save cover" busy={busy} onPress={submit} />
    </ActionCard>
  );
}

function MoveForm({ shift, onDone, onClose }: FormProps) {
  const [day, setDay] = useState(shift.date);
  const [start, setStart] = useState(shift.start);
  const [end, setEnd] = useState(shift.end);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const submit = async () =>
    setErrors(
      handle(
        await run(`/office/shifts/${shift.id}/move`, {
          shift_date: day,
          start_time: start,
          end_time: end,
        }),
        onDone
      )
    );

  return (
    <ActionCard title="Move this shift" onClose={onClose}>
      <DayStepper value={day} onChange={setDay} />
      <View className="flex-row gap-3">
        <TimeField label="From" value={start} onChange={setStart} error={first("start_time")} />
        <TimeField label="To" value={end} onChange={setEnd} error={first("end_time")} />
      </View>
      {first("shift_date") ? (
        <Typography className="text-danger">{first("shift_date")}</Typography>
      ) : null}
      <SubmitButton label="Move shift" busy={busy} onPress={submit} />
    </ActionCard>
  );
}

function CorrectForm({ shift, onDone, onClose }: FormProps) {
  const [inAt, setIn] = useState(
    shift.visit?.check_in_at ? clock(shift.visit.check_in_at) : shift.start
  );
  const [outAt, setOut] = useState(
    shift.visit?.check_out_at ? clock(shift.visit.check_out_at) : shift.end
  );
  const [reason, setReason] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const submit = async () =>
    setErrors(
      handle(
        await run(`/office/shifts/${shift.id}/correct`, {
          check_in_at: `${shift.date}T${inAt}:00+08:00`,
          check_out_at: `${shift.date}T${outAt}:00+08:00`,
          reason,
        }),
        onDone
      )
    );

  return (
    <ActionCard
      title={shift.visit ? "Correct the visit record" : "Record the visit"}
      onClose={onClose}
    >
      <Typography type="body-sm" color="muted">
        The old times and your reason are kept in the audit log.
      </Typography>
      <View className="flex-row gap-3">
        <TimeField label="Arrived" value={inAt} onChange={setIn} error={first("check_in_at")} />
        <TimeField label="Left" value={outAt} onChange={setOut} error={first("check_out_at")} />
      </View>
      <ReasonField
        label="Why"
        value={reason}
        onChange={setReason}
        placeholder="Phone battery died; confirmed with the family"
        error={first("reason")}
      />
      <SubmitButton label="Save record" busy={busy} onPress={submit} />
    </ActionCard>
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
