import { Alert, Chip } from "heroui-native";

type Color = "accent" | "default" | "success" | "warning" | "danger";

/**
 * One vocabulary of status colours across the app, so the same colour
 * means the same thing on every screen: red needs someone now, amber is
 * waiting, teal is happening, green is done, grey is over.
 */
const STATUSES: Record<string, { label: string; color: Color }> = {
  // shifts
  scheduled: { label: "Booked", color: "default" },
  in_progress: { label: "Checked in", color: "accent" },
  completed: { label: "Done", color: "success" },
  missed: { label: "Missed", color: "danger" },
  cancelled: { label: "Cancelled", color: "default" },
  late: { label: "Not checked in", color: "danger" },
  no_show: { label: "Nobody came", color: "danger" },
  // concerns
  open: { label: "Open", color: "danger" },
  investigating: { label: "Being looked into", color: "warning" },
  resolved: { label: "Resolved", color: "success" },
  closed: { label: "Closed", color: "default" },
  // invoices
  draft: { label: "Draft", color: "warning" },
  sent: { label: "Unpaid", color: "accent" },
  part_paid: { label: "Part paid", color: "accent" },
  paid: { label: "Paid", color: "success" },
  overdue: { label: "Overdue", color: "danger" },
  void: { label: "Void", color: "default" },
  // clients and care plans
  enquiry: { label: "Enquiry", color: "warning" },
  assessment: { label: "Assessment", color: "warning" },
  paused: { label: "Paused", color: "default" },
  superseded: { label: "Superseded", color: "default" },
  // people
  active: { label: "Active", color: "success" },
  invited: { label: "Not signed in yet", color: "warning" },
  suspended: { label: "Suspended", color: "danger" },
};

export function StatusChip({ status, label }: { status: string; label?: string }) {
  const s = STATUSES[status] ?? { label: status, color: "default" as Color };
  return (
    <Chip variant="soft" color={s.color} size="sm">
      <Chip.Label>{label ?? s.label}</Chip.Label>
    </Chip>
  );
}

/** A red box for something that went wrong, with what to do about it. */
export function ErrorBanner({ message, title }: { message: string; title?: string }) {
  return (
    <Alert status="danger">
      <Alert.Indicator />
      <Alert.Content>
        {title ? <Alert.Title>{title}</Alert.Title> : null}
        <Alert.Description>{message}</Alert.Description>
      </Alert.Content>
    </Alert>
  );
}
