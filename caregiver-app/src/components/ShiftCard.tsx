import { router } from "expo-router";
import { Card, Chip, Typography } from "heroui-native";
import { Pressable } from "react-native";

import type { Shift } from "@/lib/api";
import { shortDay } from "@/lib/format";

const STATUS: Record<
  Shift["status"],
  { label: string; color: "accent" | "default" | "success" | "warning" | "danger" } | null
> = {
  scheduled: null,
  in_progress: { label: "Checked in", color: "accent" },
  completed: { label: "Done", color: "success" },
  missed: { label: "Missed", color: "danger" },
  cancelled: { label: "Cancelled", color: "default" },
};

export function StatusChip({ status }: { status: Shift["status"] }) {
  const s = STATUS[status];
  if (!s) return null;
  return (
    <Chip variant="soft" color={s.color} size="sm">
      <Chip.Label>{s.label}</Chip.Label>
    </Chip>
  );
}

/** One shift in a list. The whole card is the tap target: big, for one hand. */
export function ShiftCard({
  shift,
  showDate,
  waiting,
}: {
  shift: Shift;
  showDate?: boolean;
  waiting?: boolean;
}) {
  return (
    <Pressable
      onPress={() => router.push({ pathname: "/shift/[id]", params: { id: String(shift.id) } })}
      accessibilityRole="button"
      accessibilityLabel={`${shift.client.name}, ${shift.start} to ${shift.end}`}
    >
      <Card className={shift.status === "cancelled" ? "opacity-60" : undefined}>
        <Card.Body className="gap-1">
          <Typography type="h6" weight="bold">
            {showDate ? `${shortDay(shift.date)} · ` : ""}
            {shift.start}–{shift.end}
          </Typography>
          <Typography.Heading type="h4">{shift.client.name}</Typography.Heading>
          <Typography type="body-sm" color="muted">
            {[shift.client.area, shift.service].filter(Boolean).join(" · ")}
          </Typography>
        </Card.Body>
        {(STATUS[shift.status] || waiting) && (
          <Card.Footer className="flex-row gap-2">
            <StatusChip status={shift.status} />
            {waiting && (
              <Chip variant="soft" color="warning" size="sm">
                <Chip.Label>Waiting to send</Chip.Label>
              </Chip>
            )}
          </Card.Footer>
        )}
      </Card>
    </Pressable>
  );
}
