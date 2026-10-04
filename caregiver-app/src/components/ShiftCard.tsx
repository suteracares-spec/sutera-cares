import { router } from "expo-router";
import { Card, Chip, Typography } from "heroui-native";
import { Pressable } from "react-native";

import { StatusChip } from "@/components/ui/Status";
import type { Shift } from "@/lib/api";
import { shortDay } from "@/lib/format";

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
      onPress={() =>
        router.push({ pathname: "/carer/shift/[id]", params: { id: String(shift.id) } })
      }
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
        {(shift.status !== "scheduled" || waiting) && (
          <Card.Footer className="flex-row gap-2">
            {shift.status !== "scheduled" && <StatusChip status={shift.status} />}
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
