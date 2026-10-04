import { useLocalSearchParams } from "expo-router";
import { MessageCircle, Phone, UserPlus } from "lucide-react-native";
import { Button, Card, Spinner, Typography, useThemeColor } from "heroui-native";
import { useState, type JSX } from "react";
import { Linking, View } from "react-native";

import { PORTAL_URL } from "@/components/AccountCard";
import { ChoiceList, SubmitButton, useOutcome } from "@/components/ui/Form";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { enquiryLabel as label, type EnquiryDetail, type EnquiryStatus } from "@/lib/api";
import { ago } from "@/lib/format";
import { useOffice, useOfficeAction } from "@/lib/office";

/** Malaysian numbers as wa.me wants them: digits only, country code first. */
function whatsappNumber(phone: string): string {
  const digits = phone.replace(/\D/g, "");
  return digits.startsWith("0") ? `6${digits}` : digits;
}

export default function EnquiryScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: e, error, loading, reload } = useOffice<EnquiryDetail>(`/office/enquiries/${id}`);
  const [status, setStatus] = useState<EnquiryStatus | null>(null);
  const { run, busy } = useOfficeAction();
  const handle = useOutcome();
  const [accent, accentFg] = useThemeColor(["accent", "accent-foreground"]);

  const save = async () => {
    if (!e || !status) return;
    handle(await run(`/office/enquiries/${e.id}/status`, { status }), () => {
      setStatus(null);
      reload();
    });
  };

  const convert = async () => {
    if (!e) return;
    handle(await run<{ patient_id: number }>(`/office/enquiries/${e.id}/convert`), () => reload());
  };

  return (
    <Screen
      back="Enquiries"
      title={e?.client_name}
      subtitle={e ? `${e.client_relationship ?? "Enquiry"} · ${ago(e.received_at)}` : undefined}
      refreshing={loading && !!e}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={error} />}
      {!e && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {e && (
        <>
          <StatusChip status={e.status === "new" ? "open" : e.status} label={label(e.status)} />

          <View className="flex-row gap-3">
            <Button className="flex-1" onPress={() => Linking.openURL(`tel:${e.client_phone}`)}>
              <Phone size={18} color={accentFg} />
              <Button.Label>Call</Button.Label>
            </Button>
            <Button
              className="flex-1"
              variant="secondary"
              onPress={() => Linking.openURL(`https://wa.me/${whatsappNumber(e.client_phone)}`)}
            >
              <MessageCircle size={18} color={accent} />
              <Button.Label>WhatsApp</Button.Label>
            </Button>
          </View>

          <Card>
            <Card.Body className="gap-3">
              <Fact label="Phone" value={e.client_phone} />
              {e.client_email ? <Fact label="Email" value={e.client_email} /> : null}
              <Fact
                label="Care for"
                value={`${e.patient_name ?? "Not given"}${e.patient_age ? `, ${e.patient_age}` : ""}`}
              />
              {e.area ? <Fact label="Area" value={e.area} /> : null}
              {e.patient_mobility ? <Fact label="Mobility" value={e.patient_mobility} /> : null}
              {e.needs ? <Fact label="What they need" value={e.needs} /> : null}
              {e.schedule_wanted ? <Fact label="When" value={e.schedule_wanted} /> : null}
            </Card.Body>
          </Card>

          {e.converted ? (
            <Card>
              <Card.Body className="gap-2">
                <Typography weight="semibold">This enquiry is now a client.</Typography>
                <Typography type="body-sm" color="muted">
                  Record their consent and care plan before care begins.
                </Typography>
                <Button
                  size="sm"
                  variant="outline"
                  className="self-start"
                  onPress={() => Linking.openURL(`${PORTAL_URL}/admin/patients/${e.patient_id}`)}
                >
                  Open the client on the website
                </Button>
              </Card.Body>
            </Card>
          ) : (
            <>
              <Section title="Where it stands">
                <ChoiceList
                  value={status ?? e.status}
                  onChange={setStatus}
                  options={e.statuses
                    .filter((s) => s !== "converted")
                    .map((s) => ({ value: s, label: label(s) }))}
                />
                {status && status !== e.status && (
                  <SubmitButton label="Save" busy={busy} onPress={save} />
                )}
              </Section>
              <Button variant="secondary" onPress={convert} isDisabled={busy}>
                <UserPlus size={18} color={accent} />
                <Button.Label>Make them a client</Button.Label>
              </Button>
            </>
          )}
        </>
      )}
    </Screen>
  );
}

function Fact({ label: l, value }: { label: string; value: string }) {
  return (
    <View>
      <Typography type="body-xs" weight="bold" color="muted" className="uppercase tracking-wider">
        {l}
      </Typography>
      <Typography>{value}</Typography>
    </View>
  );
}
