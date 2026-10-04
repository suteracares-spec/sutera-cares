import { Card, Typography } from "heroui-native";
import { View } from "react-native";

import { StatusChip } from "@/components/ui/Status";
import type { FamilyVisit } from "@/lib/api";
import { clock, malaysiaDate, shortDay } from "@/lib/format";

/** A visit as a family sees it: who, when, and (afterwards) what was done. */
export function FamilyVisitCard({ visit, past }: { visit: FamilyVisit; past?: boolean }) {
  const today = visit.date === malaysiaDate();
  const tomorrow = visit.date === malaysiaDate(1);
  const day = today ? "Today" : tomorrow ? "Tomorrow" : shortDay(visit.date);

  return (
    <Card>
      <Card.Body className="gap-1">
        <View className="flex-row items-center justify-between gap-2">
          <Typography weight="bold">
            {day} · {visit.start}–{visit.end}
          </Typography>
          {visit.status === "in_progress" ? (
            <StatusChip status="in_progress" label={`Arrived ${clock(visit.arrived)}`} />
          ) : visit.status === "missed" ? (
            <StatusChip status="missed" />
          ) : null}
        </View>
        <Typography>
          {visit.caregiver ?? "Caregiver to be confirmed"}
          {visit.service ? ` · ${visit.service}` : ""}
        </Typography>
        {past && visit.arrived && visit.status !== "in_progress" && (
          <Typography type="body-sm" color="muted">
            Arrived {clock(visit.arrived)}
            {visit.left ? `, left ${clock(visit.left)}` : ""}
          </Typography>
        )}
        {visit.reason ? (
          <Typography type="body-sm" color="muted">
            {visit.reason}
          </Typography>
        ) : null}
        {visit.done.length > 0 && (
          <Typography type="body-sm" className="text-success">
            ✓ {visit.done.join(" · ")}
          </Typography>
        )}
        {visit.notes ? (
          <Typography type="body-sm" className="italic">
            {visit.notes}
          </Typography>
        ) : null}
      </Card.Body>
    </Card>
  );
}
