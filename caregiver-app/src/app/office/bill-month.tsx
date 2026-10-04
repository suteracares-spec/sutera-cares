import { router } from "expo-router";
import { ChevronLeft, ChevronRight, Receipt } from "lucide-react-native";
import { Button, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { SubmitButton, useOutcome } from "@/components/ui/Form";
import { EmptyState, ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import { rm, type BillingPrepare } from "@/lib/api";
import { malaysiaDate } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/** "2026-10" moved by n months. */
function shiftMonth(month: string, n: number): string {
  const [y, m] = month.split("-").map(Number);
  const d = new Date(Date.UTC(y, m - 1 + n, 1));
  return `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, "0")}`;
}

/** Pick a month, see who has completed visits to bill, and draft their invoices. */
export default function BillMonth(): JSX.Element {
  // Last month: the usual billing run.
  const [month, setMonth] = useState(() => shiftMonth(malaysiaDate().slice(0, 7), -1));
  const { data, error, loading, reload } = useOffice<BillingPrepare>(`/office/invoices/prepare?month=${month}`);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const accent = useThemeColor("accent");

  const generate = async (client?: number) =>
    handle(
      await run<{ invoice_ids: number[] }>("/office/invoices/generate", { month, client: client ?? null }),
      (d) =>
        d.invoice_ids.length === 1
          ? router.replace({ pathname: "/office/invoice/[id]", params: { id: String(d.invoice_ids[0]) } })
          : router.back()
    );

  const total = data?.clients.reduce((sum, c) => sum + c.amount, 0) ?? 0;

  return (
    <Screen back="Invoices" title="Bill a month" refreshing={loading && !!data} onRefresh={reload}>
      <View className="flex-row items-center gap-3">
        <Button size="sm" variant="secondary" isIconOnly onPress={() => setMonth((m) => shiftMonth(m, -1))}>
          <ChevronLeft size={18} color={accent} />
        </Button>
        <Typography weight="semibold" className="flex-1 text-center">
          {data?.label ?? month}
        </Typography>
        <Button size="sm" variant="secondary" isIconOnly onPress={() => setMonth((m) => shiftMonth(m, 1))}>
          <ChevronRight size={18} color={accent} />
        </Button>
      </View>

      {error && <ErrorBanner message={error} />}
      {!data && loading && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {data &&
        (data.clients.length === 0 ? (
          <EmptyState
            icon={Receipt}
            title="Nothing to bill"
            hint={`No completed visits without an invoice in ${data.label}.`}
          />
        ) : (
          <>
            <Section title={`To bill · ${rm(total)}`}>
              <ListCard>
                {data.clients.map((c, i) => (
                  <Row
                    key={c.id}
                    initials={c.name ?? c.code}
                    title={c.name ?? c.code}
                    subtitle={`${c.shifts} ${c.shifts === 1 ? "visit" : "visits"} · ${rm(c.amount)}`}
                    trailing={
                      <Button size="sm" variant="secondary" onPress={() => generate(c.id)} isDisabled={busy}>
                        Draft
                      </Button>
                    }
                    last={i === data.clients.length - 1}
                  />
                ))}
              </ListCard>
            </Section>
            <Typography type="body-sm" color="muted">
              Drafts can be checked and adjusted before they are issued. Nothing is sent to anyone yet.
            </Typography>
            <SubmitButton
              label={data.clients.length === 1 ? "Draft the invoice" : `Draft all ${data.clients.length} invoices`}
              busy={busy}
              onPress={() => generate()}
            />
          </>
        ))}
    </Screen>
  );
}
