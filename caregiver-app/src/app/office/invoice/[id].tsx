import { router, useLocalSearchParams } from "expo-router";
import { Share2, Trash2, X } from "lucide-react-native";
import { Button, Card, Spinner, Typography, useThemeColor, useToast } from "heroui-native";
import { useState, type JSX } from "react";
import { Pressable, View } from "react-native";

import {
  ActionCard,
  ChoiceList,
  DateField,
  Field,
  ReasonField,
  SubmitButton,
  useFieldErrors,
  useOutcome,
} from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { rm, type InvoiceDetail } from "@/lib/api";
import { malaysiaDate, shortDay } from "@/lib/format";
import { shareInvoicePdf } from "@/lib/invoicePdf";
import { useAuth } from "@/lib/auth";
import { useOffice, useOfficeAction } from "@/lib/office";

type Panel = "line" | "issue" | "pay" | "void" | "delete" | null;

/** One invoice: check and adjust a draft, issue it, share it, record payments. */
export default function InvoiceScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: inv, error, loading, reload } = useOffice<InvoiceDetail>(`/office/invoices/${id}`);
  const [panel, setPanel] = useState<Panel>(null);
  const [sharing, setSharing] = useState(false);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { toast } = useToast();
  const { token } = useAuth();
  const muted = useThemeColor("muted");

  const done = () => {
    setPanel(null);
    reload();
  };

  const share = async () => {
    if (!inv) return;
    setSharing(true);
    try {
      await shareInvoicePdf(`/office/invoices/${inv.id}/html`, token);
    } catch (e) {
      toast.show({ variant: "danger", label: e instanceof Error ? e.message : "Could not make the PDF." });
    } finally {
      setSharing(false);
    }
  };

  const removeLine = async (line: number) => handle(await run(`/office/invoices/${id}/lines/${line}`, {}, "DELETE"), reload);

  const isDraft = inv?.status === "draft";

  return (
    <Screen
      back="Invoices"
      title={inv ? (isDraft ? "Draft invoice" : inv.number) : undefined}
      subtitle={inv ? `${inv.client ?? "Client"} · ${inv.period ?? ""}` : undefined}
      refreshing={loading && !!inv}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={error} />}
      {!inv && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {inv && (
        <>
          <Card>
            <Card.Body className="gap-2">
              <View className="flex-row items-center justify-between">
                <StatusChip status={inv.status} label={inv.status_label} />
                <Typography.Heading type="h4">{rm(inv.total)}</Typography.Heading>
              </View>
              <Typography color="muted">
                To {inv.bill_to ?? "nobody yet: add a bill payer on the client page"}
                {inv.bill_to_email ? ` · ${inv.bill_to_email}` : ""}
              </Typography>
              {inv.due_date && (
                <Typography color="muted">
                  Due {shortDay(inv.due_date)}
                  {inv.amount_paid > 0 ? ` · ${rm(inv.amount_paid)} paid, ${rm(inv.balance)} owed` : ""}
                </Typography>
              )}
            </Card.Body>
          </Card>

          <Button variant="secondary" onPress={share} isDisabled={sharing}>
            {sharing ? <Spinner size="sm" /> : <Share2 size={16} color={muted} />}
            <Button.Label>{isDraft ? "Preview PDF" : "Share PDF"}</Button.Label>
          </Button>

          {panel === null && inv.status !== "void" && (
            <View className="gap-2">
              {isDraft && (
                <>
                  <Button onPress={() => setPanel("issue")}>Issue invoice</Button>
                  <View className="flex-row gap-2">
                    <Button className="flex-1" variant="secondary" onPress={() => setPanel("line")}>
                      Add adjustment
                    </Button>
                    <Button className="flex-1" variant="danger-soft" onPress={() => setPanel("delete")}>
                      Delete draft
                    </Button>
                  </View>
                </>
              )}
              {inv.can_pay && <Button onPress={() => setPanel("pay")}>Record a payment</Button>}
              {inv.can_void && (
                <Button variant="danger-soft" onPress={() => setPanel("void")}>
                  Void invoice
                </Button>
              )}
            </View>
          )}

          {panel === "line" && <LineForm id={inv.id} onClose={() => setPanel(null)} onDone={done} />}
          {panel === "issue" && (
            <ActionCard title="Issue this invoice?" onClose={() => setPanel(null)}>
              <Typography type="body-sm" color="muted">
                It gets its invoice number and a due date, and can no longer be edited. Share the PDF
                with {inv.bill_to ?? "the family"} afterwards.
              </Typography>
              <SubmitButton
                label={`Issue for ${rm(inv.total)}`}
                busy={busy}
                onPress={async () => handle(await run(`/office/invoices/${inv.id}/issue`), done)}
              />
            </ActionCard>
          )}
          {panel === "pay" && <PayForm inv={inv} onClose={() => setPanel(null)} onDone={done} />}
          {panel === "void" && <VoidForm inv={inv} onClose={() => setPanel(null)} onDone={done} />}
          {panel === "delete" && (
            <ActionCard title="Delete this draft?" onClose={() => setPanel(null)}>
              <Typography type="body-sm" color="muted">
                Its visits become unbilled again, so the next run for the month picks them up.
              </Typography>
              <SubmitButton
                label="Delete draft"
                danger
                busy={busy}
                onPress={async () => handle(await run(`/office/invoices/${inv.id}`, {}, "DELETE"), () => router.back())}
              />
            </ActionCard>
          )}

          <Section title="Lines">
            <Card>
              <Card.Body className="gap-3">
                {inv.lines.map((l) => (
                  <View key={l.id} className="flex-row gap-3 items-start">
                    <View className="flex-1">
                      <Typography type="body-sm">{l.description}</Typography>
                      <Typography type="body-xs" color="muted">
                        {l.quantity} × {rm(l.rate)}
                      </Typography>
                    </View>
                    <Typography type="body-sm" weight="semibold">
                      {rm(l.amount)}
                    </Typography>
                    {isDraft && (
                      <Pressable
                        onPress={() => removeLine(l.id)}
                        accessibilityRole="button"
                        accessibilityLabel={`Remove ${l.description}`}
                        hitSlop={10}
                        disabled={busy}
                      >
                        {l.from_shift ? <X size={18} color={muted} /> : <Trash2 size={18} color={muted} />}
                      </Pressable>
                    )}
                  </View>
                ))}
                <View className="border-t border-separator pt-3 gap-1">
                  <Total label="Visits" value={rm(inv.subtotal)} />
                  {inv.adjustments !== 0 && <Total label="Adjustments" value={rm(inv.adjustments)} />}
                  <Total label="Total" value={rm(inv.total)} bold />
                </View>
              </Card.Body>
            </Card>
            {isDraft && (
              <Typography type="body-xs" color="muted">
                A removed visit is not lost: it is billed again on the next run for that month.
              </Typography>
            )}
          </Section>

          {inv.payments.length > 0 && (
            <Section title="Payments">
              <Card>
                <Card.Body className="gap-3">
                  {inv.payments.map((p) => (
                    <View key={p.id} className="flex-row justify-between gap-3">
                      <View className="flex-1">
                        <Typography type="body-sm">
                          {p.method}
                          {p.reference ? ` · ${p.reference}` : ""}
                        </Typography>
                        <Typography type="body-xs" color="muted">
                          {p.paid_on ? shortDay(p.paid_on) : ""}
                          {p.recorded_by ? ` · recorded by ${p.recorded_by}` : ""}
                        </Typography>
                      </View>
                      <Typography type="body-sm" weight="semibold">
                        {rm(p.amount)}
                      </Typography>
                    </View>
                  ))}
                </Card.Body>
              </Card>
            </Section>
          )}

          <Button
            variant="ghost"
            onPress={() => router.push({ pathname: "/office/client/[id]", params: { id: String(inv.client_id) } })}
          >
            <Button.Label>Open client</Button.Label>
          </Button>
        </>
      )}
    </Screen>
  );
}

function Total({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
  return (
    <View className="flex-row justify-between">
      <Typography type="body-sm" weight={bold ? "bold" : "normal"}>
        {label}
      </Typography>
      <Typography type="body-sm" weight={bold ? "bold" : "normal"}>
        {value}
      </Typography>
    </View>
  );
}

type FormProps = { onClose: () => void; onDone: () => void };

function LineForm({ id, onClose, onDone }: FormProps & { id: number }) {
  const [description, setDescription] = useState("");
  const [quantity, setQuantity] = useState("1");
  const [rate, setRate] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () =>
    setErrors(handle(await run(`/office/invoices/${id}/lines`, { description, quantity, rate }), onDone));

  return (
    <ActionCard title="Add an adjustment" onClose={onClose}>
      <Field
        label="Description"
        value={description}
        onChange={setDescription}
        placeholder="e.g. Public holiday surcharge, travel, discount"
        error={first("description")}
        required
      />
      <View className="flex-row gap-3">
        <View className="w-24">
          <Field label="Qty" value={quantity} onChange={setQuantity} keyboardType="numbers-and-punctuation" error={first("quantity")} />
        </View>
        <View className="flex-1">
          <Field
            label="Rate (RM)"
            value={rate}
            onChange={setRate}
            keyboardType="numbers-and-punctuation"
            hint="Negative for a discount, e.g. -20"
            error={first("rate")}
          />
        </View>
      </View>
      <SubmitButton label="Add line" busy={busy} onPress={save} />
    </ActionCard>
  );
}

function PayForm({ inv, onClose, onDone }: FormProps & { inv: InvoiceDetail }) {
  const [amount, setAmount] = useState(inv.balance.toFixed(2));
  const [method, setMethod] = useState("bank_transfer");
  const [reference, setReference] = useState("");
  const [paidOn, setPaidOn] = useState(malaysiaDate());
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () =>
    setErrors(
      handle(
        await run(`/office/invoices/${inv.id}/payments`, { amount, method, reference: reference || null, paid_on: paidOn }),
        onDone
      )
    );

  return (
    <ActionCard title="Record a payment" onClose={onClose}>
      <Field
        label="Amount (RM)"
        value={amount}
        onChange={setAmount}
        keyboardType="numbers-and-punctuation"
        hint={`${rm(inv.balance)} still owed.`}
        error={first("amount")}
        required
      />
      <ChoiceList value={method} onChange={setMethod} options={inv.methods} />
      <Field
        label="Reference"
        value={reference}
        onChange={setReference}
        placeholder="Bank or DuitNow reference"
        error={first("reference")}
      />
      <DateField label="Paid on" value={paidOn} onChange={setPaidOn} error={first("paid_on")} />
      <SubmitButton label="Record payment" busy={busy} onPress={save} />
    </ActionCard>
  );
}

function VoidForm({ inv, onClose, onDone }: FormProps & { inv: InvoiceDetail }) {
  const [reason, setReason] = useState("");
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const { setErrors, first } = useFieldErrors();

  const save = async () => setErrors(handle(await run(`/office/invoices/${inv.id}/void`, { reason }), onDone));

  return (
    <ActionCard title={`Void ${inv.number}?`} onClose={onClose}>
      <Typography type="body-sm" color="muted">
        It stays on record, marked void, so the numbering has no gaps. Its visits can be billed again.
      </Typography>
      <ReasonField label="Reason" value={reason} onChange={setReason} error={first("reason")} placeholder="e.g. Billed to the wrong person" />
      <SubmitButton label="Void invoice" danger busy={busy} onPress={save} />
    </ActionCard>
  );
}
