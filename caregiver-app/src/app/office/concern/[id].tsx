import { router, useLocalSearchParams } from "expo-router";
import { Button, Card, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import {
  ActionCard,
  ChoiceList,
  ReasonField,
  SubmitButton,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import type { OfficeConcernDetail, StaffMember } from "@/lib/api";
import { ago } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

const STATUSES: { value: OfficeConcernDetail["status"]; label: string }[] = [
  { value: "open", label: "Open" },
  { value: "investigating", label: "Being looked into" },
  { value: "resolved", label: "Resolved" },
  { value: "closed", label: "Closed, no action needed" },
];

export default function ConcernScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const {
    data: c,
    error,
    loading,
    reload,
  } = useOffice<OfficeConcernDetail>(`/office/concerns/${id}`);
  const [editing, setEditing] = useState(false);

  return (
    <Screen
      back="Concerns"
      title={c ? (c.plan_change ? "Care plan change request" : c.category) : undefined}
      subtitle={c ? `${c.client ?? "No client"} · ${ago(c.raised_at)}` : undefined}
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
            <StatusChip status={c.owner ? "closed" : "open"} label={c.owner ?? "No owner"} />
          </View>

          <Card>
            <Card.Body className="gap-2">
              <Typography type="body-sm" color="muted">
                From {c.raised_by} ({c.raised_by_role})
              </Typography>
              <Typography>{c.detail}</Typography>
              {c.shift && (
                <Button
                  size="sm"
                  variant="outline"
                  className="self-start mt-1"
                  onPress={() =>
                    router.push({
                      pathname: "/office/shift/[id]",
                      params: { id: String(c.shift_id) },
                    })
                  }
                >
                  {`Open the visit (${c.shift})`}
                </Button>
              )}
            </Card.Body>
          </Card>

          {c.resolution ? (
            <Section title="What was done">
              <Card>
                <Card.Body>
                  <Typography>{c.resolution}</Typography>
                </Card.Body>
              </Card>
            </Section>
          ) : null}

          {editing ? (
            <HandleForm
              concern={c}
              onClose={() => setEditing(false)}
              onDone={() => {
                setEditing(false);
                reload();
              }}
            />
          ) : (
            <Button onPress={() => setEditing(true)}>
              {c.owner ? "Update or resolve" : "Take it on"}
            </Button>
          )}
        </>
      )}
    </Screen>
  );
}

function HandleForm({
  concern,
  onDone,
  onClose,
}: {
  concern: OfficeConcernDetail;
  onDone: () => void;
  onClose: () => void;
}) {
  const { data: staff } = useOffice<{ staff: StaffMember[] }>("/office/staff");
  const [owner, setOwner] = useState<number | null>(concern.owner_id);
  const [status, setStatus] = useState<OfficeConcernDetail["status"]>(
    concern.status === "open" ? "investigating" : concern.status
  );
  const [resolution, setResolution] = useState(concern.resolution ?? "");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();
  const closing = status === "resolved" || status === "closed";

  const submit = async () =>
    setErrors(
      handle(
        await run(
          `/office/concerns/${concern.id}`,
          { status, assigned_to_id: owner, resolution: resolution || null },
          "PUT"
        ),
        onDone
      )
    );

  return (
    <ActionCard title="Handle this concern" onClose={onClose}>
      <Typography weight="semibold">Owner</Typography>
      {!staff ? (
        <Spinner />
      ) : (
        <ChoiceList
          value={owner}
          onChange={setOwner}
          options={[
            { value: null as number | null, label: "Nobody yet" },
            ...staff.staff.map((s) => ({ value: s.id as number | null, label: s.name })),
          ]}
        />
      )}
      <Typography weight="semibold">Status</Typography>
      <ChoiceList value={status} onChange={setStatus} options={STATUSES} />
      <ReasonField
        label={closing ? "What was done" : "What was done so far (optional)"}
        value={resolution}
        onChange={setResolution}
        error={first("resolution")}
        long
      />
      <Typography type="body-xs" color="muted">
        Office only. The family sees only whether it is with the office or resolved.
      </Typography>
      <SubmitButton label="Save" busy={busy} onPress={submit} />
    </ActionCard>
  );
}
