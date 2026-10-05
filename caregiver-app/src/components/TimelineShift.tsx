import { router } from "expo-router";
import { MapPin, MessageCircleWarning } from "lucide-react-native";
import { Card, Typography, useThemeColor } from "heroui-native";
import { Pressable, View } from "react-native";

import { Initials } from "@/components/ui/List";
import { StatusChip } from "@/components/ui/Status";
import type { OfficeShift } from "@/lib/api";
import { clock } from "@/lib/format";

/** The dot on the line: the same colours as the status chips. */
const DOT: Record<string, string> = {
  late: "bg-danger border-background",
  no_show: "bg-danger border-background",
  missed: "bg-danger border-background",
  in_progress: "bg-accent border-background",
  completed: "bg-success border-background",
  cancelled: "bg-separator border-background",
  // Still to come: a hollow teal ring.
  scheduled: "bg-background border-accent",
};

/**
 * One shift on the day's timeline: the time on the left, a dot on the line
 * coloured by its state, and a card with who is going to whom.
 */
export function TimelineShift({ shift, last }: { shift: OfficeShift; last?: boolean }) {
  const status = shift.attention ?? shift.status;
  const label = shift.status === "in_progress" ? `In since ${clock(shift.check_in_at)}` : undefined;
  const [muted, warning] = useThemeColor(["muted", "warning"]);
  const live = status === "in_progress";
  const urgent = shift.attention !== null;

  return (
    <View className="flex-row gap-3">
      {/* Time */}
      <View className="w-12 items-end pt-4">
        <Typography type="body-sm" weight="bold">
          {shift.start}
        </Typography>
        <Typography type="body-xs" color="muted">
          {shift.end}
        </Typography>
      </View>

      {/* The line and its dot */}
      <View className="items-center w-4">
        <View className="w-0.5 h-5 bg-separator" />
        <View
          className={`w-3.5 h-3.5 rounded-full border-2 ${DOT[status] ?? "bg-default border-background"}`}
          style={live ? { transform: [{ scale: 1.25 }] } : undefined}
        />
        {!last && <View className="w-0.5 flex-1 bg-separator" />}
      </View>

      {/* The visit */}
      <Pressable
        className="flex-1 pb-3"
        onPress={() =>
          router.push({ pathname: "/office/shift/[id]", params: { id: String(shift.id) } })
        }
        accessibilityRole="button"
        accessibilityLabel={`${shift.start} to ${shift.end}, ${shift.client ?? "client"}`}
      >
        <Card
          className={[
            urgent ? "border border-danger" : live ? "border border-accent" : "",
            shift.status === "cancelled" ? "opacity-60" : "",
          ].join(" ")}
        >
          <Card.Body className="gap-2">
            <View className="flex-row items-start justify-between gap-2">
              <View className="flex-1">
                <Typography weight="bold" numberOfLines={1}>
                  {shift.client ?? "Client"}
                </Typography>
                <Typography type="body-xs" color="muted" numberOfLines={1}>
                  {shift.service ?? "Visit"}
                </Typography>
              </View>
              {status !== "scheduled" && <StatusChip status={status} label={label} />}
            </View>
            <View className="flex-row items-center gap-2">
              <Initials name={shift.caregiver ?? "?"} size={26} />
              <Typography type="body-sm" className="flex-1" numberOfLines={1}>
                {shift.caregiver ?? "Nobody assigned"}
                {shift.covering ? " (covering)" : ""}
              </Typography>
              {shift.area ? (
                <View className="flex-row items-center gap-1">
                  <MapPin size={12} color={muted} />
                  <Typography type="body-xs" color="muted">
                    {shift.area}
                  </Typography>
                </View>
              ) : null}
            </View>
            {shift.concern && (
              <View className="flex-row items-center gap-1.5">
                <MessageCircleWarning size={14} color={warning} />
                <Typography type="body-xs" className="text-warning">
                  Concern flagged on this visit
                </Typography>
              </View>
            )}
          </Card.Body>
        </Card>
      </Pressable>
    </View>
  );
}
