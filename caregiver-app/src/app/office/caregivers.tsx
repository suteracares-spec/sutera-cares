import { router } from "expo-router";
import { HeartHandshake, Plus, Search } from "lucide-react-native";
import { Button, Chip, Input, Spinner, TextField, useThemeColor } from "heroui-native";
import { useEffect, useState, type JSX } from "react";
import { ScrollView, View } from "react-native";

import { EmptyState, ListCard, Row } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { humanise, type CaregiverStatus, type CaregiverSummary } from "@/lib/api";
import { useOffice } from "@/lib/office";

const FILTERS: (CaregiverStatus | null)[] = [null, "active", "vetting", "applicant", "inactive"];

/** Every caregiver: who is placeable, whose vetting is about to lapse. */
export default function Caregivers(): JSX.Element {
  const [typed, setTyped] = useState("");
  const [q, setQ] = useState("");
  const [status, setStatus] = useState<CaregiverStatus | null>(null);
  const [muted, accentFg] = useThemeColor(["muted", "accent-foreground"]);

  useEffect(() => {
    const t = setTimeout(() => setQ(typed.trim()), 350);
    return () => clearTimeout(t);
  }, [typed]);

  const { data, error, loading, reload } = useOffice<{ caregivers: CaregiverSummary[] }>(
    `/office/caregivers?q=${encodeURIComponent(q)}${status ? `&status=${status}` : ""}`
  );

  return (
    <Screen
      back="More"
      title="Caregivers"
      action={
        <Button size="sm" onPress={() => router.push("/office/caregiver-form")}>
          <Plus size={16} color={accentFg} />
          <Button.Label>New</Button.Label>
        </Button>
      }
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      <TextField>
        <View className="flex-row items-center gap-2">
          <Search size={18} color={muted} />
          <View className="flex-1">
            <Input
              variant="secondary"
              value={typed}
              onChangeText={setTyped}
              placeholder="Name, code, area or skill"
              autoCapitalize="none"
            />
          </View>
        </View>
      </TextField>

      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
        {FILTERS.map((f) => (
          <Chip key={f ?? "all"} variant={status === f ? "primary" : "secondary"} onPress={() => setStatus(f)}>
            <Chip.Label>{f ? humanise(f) : "All"}</Chip.Label>
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
        (data.caregivers.length === 0 ? (
          <EmptyState icon={HeartHandshake} title={q || status ? "No caregivers match" : "No caregivers yet"} />
        ) : (
          <ListCard>
            {data.caregivers.map((c, i) => (
              <Row
                key={c.id}
                initials={c.name ?? c.code}
                title={c.name ?? c.code}
                subtitle={`${c.code}${c.area ? ` · ${c.area}` : ""}${c.check_expiring ? " · police check lapsing" : ""}`}
                trailing={
                  c.status === "active" ? (
                    <StatusChip
                      status={c.placeable ? "active" : "open"}
                      label={c.placeable ? "Placeable" : "Not placeable"}
                    />
                  ) : (
                    <StatusChip status="draft" label={humanise(c.status)} />
                  )
                }
                onPress={() =>
                  router.push({ pathname: "/office/caregiver/[id]", params: { id: String(c.id) } })
                }
                last={i === data.caregivers.length - 1}
              />
            ))}
          </ListCard>
        ))}
    </Screen>
  );
}
