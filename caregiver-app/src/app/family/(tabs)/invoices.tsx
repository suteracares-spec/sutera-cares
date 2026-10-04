import { Receipt, Share2 } from "lucide-react-native";
import { Card, Spinner, Typography, useThemeColor, useToast } from "heroui-native";
import { useState, type JSX } from "react";
import { Pressable, View } from "react-native";

import { EmptyState, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { rm } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useFamilyClient } from "@/lib/family";
import { shortDay } from "@/lib/format";
import { shareInvoicePdf } from "@/lib/invoicePdf";

/** Invoices, when the office has shared them with this account, and where to pay. */
export default function FamilyInvoices(): JSX.Element {
  const { data: c, error, loading, reload } = useFamilyClient();
  const { token } = useAuth();
  const { toast } = useToast();
  const [opening, setOpening] = useState<number | null>(null);
  const muted = useThemeColor("muted");

  const open = async (id: number) => {
    setOpening(id);
    try {
      await shareInvoicePdf(`/family/invoices/${id}/html`, token);
    } catch (e) {
      toast.show({ variant: "danger", label: e instanceof Error ? e.message : "Could not open the invoice." });
    } finally {
      setOpening(null);
    }
  };

  return (
    <Screen title="Invoices" subtitle={c?.name} refreshing={loading && !!c} onRefresh={reload}>
      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!c && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {c && !c.can_view_invoices && (
        <EmptyState
          icon={Receipt}
          title="Invoices go to someone else"
          hint="Invoices are not shared with your account. Ask your coordinator if you would like to see them."
        />
      )}

      {c && c.can_view_invoices && (
        <>
          {c.invoices.length === 0 ? (
            <EmptyState icon={Receipt} title="No invoices yet" />
          ) : (
            <View className="gap-3">
              {c.invoices.map((i) => (
                <Pressable
                  key={i.id}
                  onPress={() => open(i.id)}
                  accessibilityRole="button"
                  accessibilityLabel={`Open invoice ${i.number}`}
                  disabled={opening !== null}
                >
                  <Card>
                    <Card.Body className="gap-1">
                      <View className="flex-row items-center justify-between">
                        <Typography weight="bold">{i.number}</Typography>
                        <StatusChip status={i.status} label={i.status_label} />
                      </View>
                      <Typography>
                        {i.period} · {rm(i.total)}
                      </Typography>
                      <View className="flex-row items-center justify-between">
                        <Typography type="body-sm" color="muted">
                          {i.balance > 0 && i.due_date
                            ? `${rm(i.balance)} due ${shortDay(i.due_date)}`
                            : "Paid, thank you"}
                        </Typography>
                        {opening === i.id ? <Spinner size="sm" /> : <Share2 size={16} color={muted} />}
                      </View>
                    </Card.Body>
                  </Card>
                </Pressable>
              ))}
            </View>
          )}

          {c.bank && (
            <Section title="How to pay">
              <Card>
                <Card.Body className="gap-1">
                  <Typography>Bank transfer or DuitNow to:</Typography>
                  {c.bank.bank ? <Typography weight="semibold">{c.bank.bank}</Typography> : null}
                  {c.bank.account_name ? <Typography>{c.bank.account_name}</Typography> : null}
                  <Typography weight="bold" selectable>
                    {c.bank.account_number}
                  </Typography>
                  <Typography type="body-sm" color="muted">
                    Put the invoice number as the reference.
                  </Typography>
                </Card.Body>
              </Card>
            </Section>
          )}
        </>
      )}
    </Screen>
  );
}
