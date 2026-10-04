import { Spinner, Typography } from "heroui-native";
import type { JSX } from "react";
import { View } from "react-native";

import { FamilyVisitCard } from "@/components/FamilyVisitCard";
import { Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import type { MyCare } from "@/lib/api";
import { useOffice } from "@/lib/office";

/** Deliberately small: who is coming, and when. */
export default function MyVisits(): JSX.Element {
  const { data, error, loading, reload } = useOffice<MyCare>("/my-care");

  return (
    <Screen
      title="My visits"
      subtitle={data?.name}
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!data && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}
      {data && (
        <Section title="This week">
          {data.coming.length === 0 ? (
            <Typography color="muted">No visits booked in the next seven days.</Typography>
          ) : (
            data.coming.map((v) => <FamilyVisitCard key={v.id} visit={v} />)
          )}
        </Section>
      )}
    </Screen>
  );
}
