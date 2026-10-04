import { CalendarDays } from "lucide-react-native";
import { Card, Chip, Spinner, Typography } from "heroui-native";
import type { JSX } from "react";
import { ScrollView, View } from "react-native";

import { FamilyVisitCard } from "@/components/FamilyVisitCard";
import { EmptyState, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import { useFamily, useFamilyClient } from "@/lib/family";
import { shortDay } from "@/lib/format";

/** Who is coming this week, what happened on recent visits, and what the caregiver does. */
export default function FamilyVisits(): JSX.Element {
  const { clients, selected, select, error: listError } = useFamily();
  const { data: c, error, loading, reload } = useFamilyClient();

  return (
    <Screen
      title={c?.name ?? "Visits"}
      subtitle="Who is coming, and how it went"
      refreshing={loading && !!c}
      onRefresh={reload}
    >
      {clients && clients.length > 1 && (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
          {clients.map((p) => (
            <Chip key={p.id} variant={selected === p.id ? "primary" : "secondary"} onPress={() => select(p.id)}>
              <Chip.Label>{p.name}</Chip.Label>
            </Chip>
          ))}
        </ScrollView>
      )}

      {(error || listError) && <ErrorBanner message={`${error ?? listError} Pull down to try again.`} />}
      {clients && clients.length === 0 && (
        <EmptyState
          icon={CalendarDays}
          title="Nobody linked yet"
          hint="Ask the office to link your account to the person you look after."
        />
      )}
      {!c && !error && clients?.length !== 0 && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {c && (
        <>
          <Section title="Coming up">
            {c.coming.length === 0 ? (
              <Typography color="muted">No visits booked in the next seven days.</Typography>
            ) : (
              c.coming.map((v) => <FamilyVisitCard key={v.id} visit={v} />)
            )}
          </Section>

          <Section title="The last two weeks">
            {c.visits.length === 0 ? (
              <Typography color="muted">No visits recorded yet.</Typography>
            ) : (
              c.visits.map((v) => <FamilyVisitCard key={v.id} visit={v} past />)
            )}
            {!c.can_view_notes && c.visits.length > 0 && (
              <Typography type="body-xs" color="muted">
                Visit notes are not shared with your account. Ask your coordinator if you would like to
                see them.
              </Typography>
            )}
          </Section>

          {c.plan && (
            <Section title="What the caregiver does">
              <Card>
                <Card.Body className="gap-2">
                  {c.plan.tasks.map((t, i) => (
                    <View key={i} className="flex-row justify-between gap-3">
                      <Typography type="body-sm" className="flex-1">
                        {t.description}
                      </Typography>
                      {t.frequency ? (
                        <Typography type="body-xs" color="muted">
                          {t.frequency}
                        </Typography>
                      ) : null}
                    </View>
                  ))}
                  {c.plan.agreed_at && (
                    <Typography type="body-xs" color="muted">
                      Care plan agreed {shortDay(c.plan.agreed_at)}
                      {c.plan.agreed_by ? ` by ${c.plan.agreed_by}` : ""}.
                    </Typography>
                  )}
                </Card.Body>
              </Card>
            </Section>
          )}
        </>
      )}
    </Screen>
  );
}
