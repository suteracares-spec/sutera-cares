import { Spinner } from "heroui-native";
import type { JSX } from "react";
import { View } from "react-native";

import { ConcernForm } from "@/components/ConcernForm";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import { useFamilyClient } from "@/lib/family";

/** A worry, a complaint, or a change to the care plan: it reaches a person at the office. */
export default function FamilyTell(): JSX.Element {
  const { data: c, error, loading, reload } = useFamilyClient();

  return (
    <Screen
      title="Tell us"
      subtitle={c ? `About ${c.name}` : undefined}
      refreshing={loading && !!c}
      onRefresh={reload}
    >
      {error && <ErrorBanner message={error} />}
      {!c && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}
      {c && (
        <ConcernForm
          path={`/family/clients/${c.id}/concerns`}
          categories={c.categories}
          concerns={c.concerns}
          onSent={reload}
        />
      )}
    </Screen>
  );
}
