import { router } from "expo-router";
import { CalendarPlus, Receipt } from "lucide-react-native";
import { Button, Card, Chip, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { ScrollView, View } from "react-native";

import { EmptyState, ListCard, Row } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { rm, type InvoiceSummary } from "@/lib/api";
import { shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

const FILTERS = [
  { value: "open", label: "Open" },
  { value: "overdue", label: "Overdue" },
  { value: "paid", label: "Paid" },
  { value: "void", label: "Void" },
  { value: "all", label: "All" },
];

/** Invoices: what is owed, what is overdue, and drafts waiting to be issued. */
export default function Billing(): JSX.Element {
  const [show, setShow] = useState("open");
  const accentFg = useThemeColor("accent-foreground");
  const { data, error, loading, reload } = useOffice<{
    outstanding: number;
    overdue: number;
    drafts: number;
    invoices: InvoiceSummary[];
  }>(`/office/invoices?show=${show}`);

  return (
    <Screen
      back="More"
      title="Invoices"
      action={
        <Button size="sm" onPress={() => router.push("/office/bill-month")}>
          <CalendarPlus size={16} color={accentFg} />
          <Button.Label>Bill a month</Button.Label>
        </Button>
      }
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      {data && (
        <View className="flex-row gap-3">
          <Total label="Owed" value={rm(data.outstanding)} />
          <Total label="Overdue" value={rm(data.overdue)} danger={data.overdue > 0} />
          <Total label="Drafts" value={String(data.drafts)} />
        </View>
      )}

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
        {FILTERS.map((f) => (
          <Chip key={f.value} variant={show === f.value ? "primary" : "secondary"} onPress={() => setShow(f.value)}>
            <Chip.Label>{f.label}</Chip.Label>
          </Chip>
        ))}
      </ScrollView>

      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!data && loading && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {data &&
        (data.invoices.length === 0 ? (
          <EmptyState
            icon={Receipt}
            title="No invoices here"
            hint={show === "open" ? "Bill a month to draft invoices from completed visits." : undefined}
          />
        ) : (
          <ListCard>
            {data.invoices.map((i, n) => (
              <Row
                key={i.id}
                initials={i.client ?? "?"}
                title={i.client ?? "Client"}
                subtitle={`${i.status === "draft" ? "Draft" : i.number} · ${i.period ?? ""} · ${rm(i.total)}${
                  i.due_date && i.balance > 0 && i.status !== "void" ? ` · due ${shortDay(i.due_date)}` : ""
                }`}
                trailing={<StatusChip status={i.status} label={i.status_label} />}
                onPress={() => router.push({ pathname: "/office/invoice/[id]", params: { id: String(i.id) } })}
                last={n === data.invoices.length - 1}
              />
            ))}
          </ListCard>
        ))}
    </Screen>
  );
}

function Total({ label, value, danger }: { label: string; value: string; danger?: boolean }) {
  return (
    <Card className="flex-1">
      <Card.Body className="gap-1">
        <Typography type="body-xs" weight="bold" color="muted" className="uppercase tracking-wider">
          {label}
        </Typography>
        <Typography weight="bold" className={danger ? "text-danger" : undefined} numberOfLines={1} adjustsFontSizeToFit>
          {value}
        </Typography>
      </Card.Body>
    </Card>
  );
}
