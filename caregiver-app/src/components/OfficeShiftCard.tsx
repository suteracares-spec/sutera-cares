import { router } from "expo-router";
import { Card, Chip, Typography } from "heroui-native";
import { Pressable, View } from "react-native";

import { StatusChip } from "@/components/ui/Status";
import type { OfficeShift } from "@/lib/api";
import { clock } from "@/lib/format";

/** A shift as the office sees it: time, client, who is going, and its state. */
export function OfficeShiftCard({ shift }: { shift: OfficeShift }) {
  const status = shift.attention ?? shift.status;
  const label = shift.status === "in_progress" ? `In since ${clock(shift.check_in_at)}` : undefined;

  return (
    <Pressable
      onPress={() =>
        router.push({ pathname: "/office/shift/[id]", params: { id: String(shift.id) } })
      }
      accessibilityRole="button"
    >
      <Card className={shift.status === "cancelled" ? "opacity-60" : undefined}>
        <Card.Body className="gap-1">
          <View className="flex-row items-center justify-between">
            <Typography weight="bold">
              {shift.start}–{shift.end}
            </Typography>
            {status !== "scheduled" && <StatusChip status={status} label={label} />}
          </View>
          <Typography.Heading type="h5">{shift.client}</Typography.Heading>
          <Typography type="body-sm" color="muted">
            {shift.caregiver ?? "Nobody assigned"}
            {shift.covering ? " (covering)" : ""}
            {shift.area ? ` · ${shift.area}` : ""}
          </Typography>
          {shift.concern && (
            <Chip variant="soft" color="warning" size="sm" className="self-start mt-1">
              <Chip.Label>Concern flagged</Chip.Label>
            </Chip>
          )}
        </Card.Body>
      </Card>
    </Pressable>
  );
}
