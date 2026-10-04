import { Spinner } from "heroui-native";
import type { JSX } from "react";
import { View } from "react-native";

import { ConcernForm } from "@/components/ConcernForm";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner } from "@/components/ui/Status";
import type { MyCare } from "@/lib/api";
import { useOffice } from "@/lib/office";

export default function MyTell(): JSX.Element {
  const { data, error, loading, reload } = useOffice<MyCare>("/my-care");

  return (
    <Screen title="Tell us" refreshing={loading && !!data} onRefresh={reload}>
      {error && <ErrorBanner message={error} />}
      {!data && !error && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}
      {data && (
        <ConcernForm
          path="/my-care/concerns"
          categories={data.categories}
          concerns={data.concerns}
          onSent={reload}
        />
      )}
    </Screen>
  );
}
