import { ScrollText } from "lucide-react-native";
import { Button, Card, Chip, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { ScrollView, View } from "react-native";

import { EmptyState } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import { humanise, request, type AuditEntry, type AuditPage } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { clock, shortDay } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** Who looked at what, and who changed what. Read-only, administrators only. */
export default function Audit(): JSX.Element {
  const [user, setUser] = useState<number | null>(null);
  const [action, setAction] = useState<string | null>(null);
  const query = `${user ? `&user=${user}` : ""}${action ? `&action=${encodeURIComponent(action)}` : ""}`;
  const { data, error, loading, reload } = useOffice<AuditPage>(`/office/audit?${query}`);
  const { token } = useAuth();

  // Older pages, fetched on demand, for the filters they were fetched with.
  const [older, setOlder] = useState<{ query: string; entries: AuditEntry[]; more: boolean } | null>(null);
  const [fetching, setFetching] = useState(false);
  const extra = older?.query === query ? older : null;
  const entries = [...(data?.entries ?? []), ...(extra?.entries ?? [])];
  const more = extra ? extra.more : (data?.more ?? false);

  const loadMore = async () => {
    const last = entries[entries.length - 1];
    if (!last) return;
    setFetching(true);
    try {
      const page = await request<AuditPage>(`/office/audit?before=${last.id}${query}`, { token });
      setOlder({ query, entries: [...(extra?.entries ?? []), ...page.entries], more: page.more });
    } finally {
      setFetching(false);
    }
  };

  return (
    <Screen
      back="More"
      title="Audit log"
      subtitle="Who looked at what, and who changed what"
      refreshing={loading && !!data}
      onRefresh={() => {
        setOlder(null);
        reload();
      }}
    >
      {data && (
        <>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
            <Chip variant={user === null ? "primary" : "secondary"} onPress={() => setUser(null)}>
              <Chip.Label>Everyone</Chip.Label>
            </Chip>
            {data.users.map((u) => (
              <Chip key={u.id} variant={user === u.id ? "primary" : "secondary"} onPress={() => setUser(u.id)}>
                <Chip.Label>{u.name}</Chip.Label>
              </Chip>
            ))}
          </ScrollView>
          <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
            <Chip variant={action === null ? "primary" : "secondary"} onPress={() => setAction(null)}>
              <Chip.Label>All actions</Chip.Label>
            </Chip>
            {data.actions.map((a) => (
              <Chip key={a} variant={action === a ? "primary" : "secondary"} onPress={() => setAction(a)}>
                <Chip.Label>{humanise(a)}</Chip.Label>
              </Chip>
            ))}
          </ScrollView>
        </>
      )}

      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!data && loading && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {data &&
        (entries.length === 0 ? (
          <EmptyState icon={ScrollText} title="Nothing logged" />
        ) : (
          <Card>
            <Card.Body className="gap-3">
              {entries.map((e, i) => (
                <View key={e.id} className={`gap-0.5 ${i > 0 ? "border-t border-separator pt-3" : ""}`}>
                  <View className="flex-row justify-between gap-2">
                    <Typography type="body-sm" weight="semibold" className="flex-1">
                      {humanise(e.action)}
                      {e.subject ? ` · ${e.subject}` : ""}
                    </Typography>
                    <Typography type="body-xs" color="muted">
                      {e.at ? `${shortDay(e.at.slice(0, 10))} ${clock(e.at)}` : ""}
                    </Typography>
                  </View>
                  <Typography type="body-xs" color="muted">
                    {e.user ?? "Not signed in"}
                    {e.ip ? ` · ${e.ip}` : ""}
                  </Typography>
                  {e.detail ? <Typography type="body-sm">{e.detail}</Typography> : null}
                </View>
              ))}
            </Card.Body>
          </Card>
        ))}

      {more && (
        <Button variant="secondary" onPress={loadMore} isDisabled={fetching}>
          {fetching ? "Loading…" : "Older entries"}
        </Button>
      )}
    </Screen>
  );
}
