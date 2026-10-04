import { router } from "expo-router";
import { Plus, Search, Users } from "lucide-react-native";
import { Button, Chip, Input, Spinner, TextField, useThemeColor } from "heroui-native";
import { useEffect, useState, type JSX } from "react";
import { ScrollView, View } from "react-native";

import { EmptyState, ListCard, Row } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { humanise, type ClientStatus, type ClientSummary } from "@/lib/api";
import { useOffice } from "@/lib/office";

const FILTERS: (ClientStatus | null)[] = [
  null,
  "active",
  "assessment",
  "enquiry",
  "paused",
  "closed",
];

/** Every client, searchable by name, code or area. */
export default function Clients(): JSX.Element {
  const [typed, setTyped] = useState("");
  const [q, setQ] = useState("");
  const [status, setStatus] = useState<ClientStatus | null>(null);
  const [muted, accentFg] = useThemeColor(["muted", "accent-foreground"]);

  // Search once typing pauses, not on every key.
  useEffect(() => {
    const t = setTimeout(() => setQ(typed.trim()), 350);
    return () => clearTimeout(t);
  }, [typed]);

  const query = `/office/clients?q=${encodeURIComponent(q)}${status ? `&status=${status}` : ""}`;
  const { data, error, loading, reload } = useOffice<{ clients: ClientSummary[] }>(query);

  return (
    <Screen
      title="Clients"
      subtitle={data ? `${data.clients.length} shown` : undefined}
      action={
        <Button size="sm" onPress={() => router.push("/office/client-form")}>
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
              placeholder="Name, code or area"
              autoCapitalize="none"
              returnKeyType="search"
            />
          </View>
        </View>
      </TextField>

      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerClassName="gap-2"
      >
        {FILTERS.map((f) => (
          <Chip
            key={f ?? "all"}
            variant={status === f ? "primary" : "secondary"}
            onPress={() => setStatus(f)}
          >
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
        (data.clients.length === 0 ? (
          <EmptyState
            icon={Users}
            title={q || status ? "No clients match" : "No clients yet"}
            hint={q || status ? undefined : "Add one with New, or convert an enquiry."}
          />
        ) : (
          <ListCard>
            {data.clients.map((c, i) => (
              <Row
                key={c.id}
                initials={c.name}
                title={c.name}
                subtitle={`${c.code}${c.area ? ` · ${c.area}` : ""}${c.consent ? "" : " · no consent recorded"}`}
                trailing={<StatusChip status={c.status} />}
                onPress={() =>
                  router.push({ pathname: "/office/client/[id]", params: { id: String(c.id) } })
                }
                last={i === data.clients.length - 1}
              />
            ))}
          </ListCard>
        ))}
    </Screen>
  );
}
