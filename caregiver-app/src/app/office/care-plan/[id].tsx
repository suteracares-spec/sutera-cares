import { router, useLocalSearchParams } from "expo-router";
import { Plus, Trash2 } from "lucide-react-native";
import { Alert, Button, Card, Chip, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { ScrollView, View } from "react-native";

import {
  ActionCard,
  DateField,
  Field,
  SubmitButton,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import type { CarePlanDetail, ClientOptions, PlanTask } from "@/lib/api";
import { malaysiaDate, shortDay } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/** A care plan: edited freely as a draft, read-only once agreed. */
export default function CarePlanScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: plan, error, reload } = useOffice<CarePlanDetail>(`/office/care-plans/${id}`);
  const { data: options } = useOffice<ClientOptions>("/office/client-options");

  if (error) {
    return (
      <Screen back="Client" title="Care plan">
        <ErrorBanner message={error} />
      </Screen>
    );
  }
  if (!plan || !options) {
    return (
      <Screen back="Client" title="Care plan">
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      </Screen>
    );
  }
  return plan.status === "draft" ? (
    <Draft key={plan.id} plan={plan} options={options} onSaved={reload} />
  ) : (
    <Agreed plan={plan} options={options} />
  );
}

function Agreed({ plan, options }: { plan: CarePlanDetail; options: ClientOptions }) {
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();

  const revise = async () =>
    handle(await run<CarePlanDetail>(`/office/clients/${plan.patient_id}/care-plans`), (draft) =>
      router.replace({ pathname: "/office/care-plan/[id]", params: { id: String(draft.id) } })
    );

  const groups = Object.entries(options.categories)
    .map(([key, label]) => [label, plan.tasks.filter((t) => t.category === key)] as const)
    .filter(([, tasks]) => tasks.length > 0);

  return (
    <Screen back="Client" title={`Care plan v${plan.version}`} subtitle={plan.client ?? undefined}>
      <StatusChip status={plan.status} label={plan.status === "active" ? "Current" : undefined} />

      <Card>
        <Card.Body className="gap-1">
          <Typography>
            From {plan.effective_from ? shortDay(plan.effective_from) : "?"}
            {plan.effective_to ? ` to ${shortDay(plan.effective_to)}` : ""}
          </Typography>
          <Typography type="body-sm" color="muted">
            Agreed by {plan.agreed_by ?? "?"}
            {plan.agreed_at ? ` on ${shortDay(plan.agreed_at)}` : ""}
            {plan.author ? ` · written by ${plan.author}` : ""}
          </Typography>
        </Card.Body>
      </Card>

      {groups.map(([label, tasks]) => (
        <Section key={label} title={label}>
          <Card>
            <Card.Body className="gap-2">
              {tasks.map((t, i) => (
                <View key={t.id ?? i} className="flex-row justify-between gap-3">
                  <Typography className="flex-1">{t.description}</Typography>
                  <Typography type="body-sm" color="muted">
                    {options.frequencies[t.frequency]}
                    {t.time_of_day !== "any"
                      ? `, ${options.times[t.time_of_day]?.toLowerCase()}`
                      : ""}
                  </Typography>
                </View>
              ))}
            </Card.Body>
          </Card>
        </Section>
      ))}

      {plan.notes ? (
        <Section title="How this person likes things done">
          <Card>
            <Card.Body>
              <Typography>{plan.notes}</Typography>
            </Card.Body>
          </Card>
        </Section>
      ) : null}

      {plan.status === "active" && (
        <SubmitButton label="Revise this plan" busy={busy} onPress={revise} />
      )}
    </Screen>
  );
}

function Draft({
  plan,
  options,
  onSaved,
}: {
  plan: CarePlanDetail;
  options: ClientOptions;
  onSaved: () => void;
}) {
  const [from, setFrom] = useState(plan.effective_from ?? malaysiaDate());
  const [notes, setNotes] = useState(plan.notes ?? "");
  const [tasks, setTasks] = useState<PlanTask[]>(plan.tasks);
  const [agreedBy, setAgreedBy] = useState("");
  const [agreedAt, setAgreedAt] = useState(malaysiaDate());
  const [mode, setMode] = useState<null | "activate" | "discard">(null);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first, errors } = useFieldErrors();
  const [accent, danger] = useThemeColor(["accent", "danger"]);

  const update = (i: number, patch: Partial<PlanTask>) =>
    setTasks((ts) => ts.map((t, n) => (n === i ? { ...t, ...patch } : t)));

  const save = async () =>
    setErrors(
      handle(
        await run<CarePlanDetail>(
          `/office/care-plans/${plan.id}`,
          { effective_from: from, notes: notes || null, tasks },
          "PUT"
        ),
        onSaved
      )
    );

  const activate = async () => {
    // Save first, so what is agreed is what is on screen.
    const saved = await run<CarePlanDetail>(
      `/office/care-plans/${plan.id}`,
      { effective_from: from, notes: notes || null, tasks },
      "PUT"
    );
    if (!saved.ok) return setErrors(handle(saved, () => {}));
    setErrors(
      handle(
        await run(`/office/care-plans/${plan.id}/activate`, {
          agreed_by: agreedBy,
          agreed_at: agreedAt,
        }),
        onSaved
      )
    );
  };

  const discard = async () =>
    handle(await run(`/office/care-plans/${plan.id}`, {}, "DELETE"), () => router.back());

  return (
    <Screen back="Client" title={`Care plan v${plan.version}`} subtitle={plan.client ?? undefined}>
      <StatusChip status="draft" label="Draft, not yet agreed" />

      <DateField
        label="Takes effect from"
        value={from}
        onChange={setFrom}
        error={first("effective_from")}
      />

      <Section title={`Tasks (${tasks.length})`}>
        <Typography type="body-sm" color="muted">
          What the caregiver does, in plain words they can tick off. No injections, wound dressing,
          IV lines, catheters or dose changes: clinical tasks are refused.
        </Typography>
        {tasks.map((t, i) => (
          <Card key={i}>
            <Card.Body className="gap-2">
              <Field
                label={`Task ${i + 1}`}
                value={t.description}
                onChange={(d) => update(i, { description: d })}
                error={errors[`tasks.${i}.description`]?.[0] ?? errors[`tasks.${i}.category`]?.[0]}
              />
              <ChipRow
                options={options.categories}
                value={t.category}
                onChange={(category) => update(i, { category })}
              />
              <ChipRow
                options={options.frequencies}
                value={t.frequency}
                onChange={(frequency) => update(i, { frequency })}
              />
              <ChipRow
                options={options.times}
                value={t.time_of_day}
                onChange={(time_of_day) => update(i, { time_of_day })}
              />
              <Button
                size="sm"
                variant="ghost"
                className="self-end"
                onPress={() => setTasks((ts) => ts.filter((_, n) => n !== i))}
              >
                <Trash2 size={14} color={danger} />
                <Button.Label className="text-danger">Remove</Button.Label>
              </Button>
            </Card.Body>
          </Card>
        ))}
        <Button
          variant="secondary"
          onPress={() =>
            setTasks((ts) => [
              ...ts,
              {
                category: "personal_care",
                description: "",
                frequency: "every_visit",
                time_of_day: "any",
              },
            ])
          }
        >
          <Plus size={16} color={accent} />
          <Button.Label>Add a task</Button.Label>
        </Button>
      </Section>

      <Field
        label="How this person likes things done"
        value={notes}
        onChange={setNotes}
        multiline
        placeholder="e.g. Prefers a female caregiver. Hard of hearing on the left."
        hint="What a new caregiver should read before their first visit. Encrypted at rest."
      />

      <SubmitButton label="Save draft" busy={busy} onPress={save} />

      {mode === "activate" ? (
        <ActionCard title={`Activate version ${plan.version}`} onClose={() => setMode(null)}>
          {!plan.consent && (
            <Alert status="danger">
              <Alert.Indicator />
              <Alert.Content>
                <Alert.Description>
                  This client has no recorded consent. Record it on the client first.
                </Alert.Description>
              </Alert.Content>
            </Alert>
          )}
          <Typography type="body-sm" color="muted">
            Caregivers work from it once activated, and it can no longer be edited.
          </Typography>
          <Field
            label="Agreed by"
            value={agreedBy}
            onChange={setAgreedBy}
            placeholder="e.g. Puan Aminah, or her daughter Nor Hayati"
            error={first("agreed_by")}
            required
          />
          <DateField
            label="Agreed on"
            value={agreedAt}
            onChange={setAgreedAt}
            error={first("agreed_at")}
          />
          <SubmitButton label="Activate" busy={busy} onPress={activate} />
        </ActionCard>
      ) : mode === "discard" ? (
        <ActionCard title="Discard this draft?" onClose={() => setMode(null)}>
          <Typography type="body-sm" color="muted">
            Only drafts can be discarded. Agreed versions are kept.
          </Typography>
          <SubmitButton label="Discard draft" busy={busy} onPress={discard} danger />
        </ActionCard>
      ) : (
        <View className="flex-row gap-3">
          <Button className="flex-1" variant="secondary" onPress={() => setMode("activate")}>
            Activate…
          </Button>
          <Button className="flex-1" variant="danger-soft" onPress={() => setMode("discard")}>
            Discard…
          </Button>
        </View>
      )}
    </Screen>
  );
}

/** Pick one value from a small set, shown as a scrolling row of chips. */
function ChipRow({
  options,
  value,
  onChange,
}: {
  options: Record<string, string>;
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
      {Object.entries(options).map(([key, label]) => (
        <Chip
          key={key}
          size="sm"
          variant={value === key ? "primary" : "secondary"}
          onPress={() => onChange(key)}
        >
          <Chip.Label>{label}</Chip.Label>
        </Chip>
      ))}
    </ScrollView>
  );
}
