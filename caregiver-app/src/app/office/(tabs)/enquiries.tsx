import { router } from "expo-router";
import { Inbox } from "lucide-react-native";
import { Button, Spinner } from "heroui-native";
import { useState, type JSX } from "react";
import { View } from "react-native";

import { EmptyState, ListCard, Row } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { ErrorBanner, StatusChip } from "@/components/ui/Status";
import { enquiryLabel as label, type Enquiry } from "@/lib/api";
import { ago } from "@/lib/format";
import { useOffice } from "@/lib/office";

/** Quote requests from the website, newest and unanswered first. */
export default function Enquiries(): JSX.Element {
  const [show, setShow] = useState<"open" | "closed">("open");
  const { data, error, loading, reload } = useOffice<{ new_count: number; enquiries: Enquiry[] }>(
    `/office/enquiries?show=${show}`
  );

  return (
    <Screen
      title="Enquiries"
      subtitle={data?.new_count ? `${data.new_count} new` : "From the website"}
      refreshing={loading && !!data}
      onRefresh={reload}
    >
      <View className="flex-row gap-2">
        <Button
          size="sm"
          variant={show === "open" ? "primary" : "secondary"}
          onPress={() => setShow("open")}
        >
          Open
        </Button>
        <Button
          size="sm"
          variant={show === "closed" ? "primary" : "secondary"}
          onPress={() => setShow("closed")}
        >
          Closed
        </Button>
      </View>

      {error && <ErrorBanner message={`${error} Pull down to try again.`} />}
      {!data && loading && (
        <View className="py-16 items-center">
          <Spinner size="lg" />
        </View>
      )}

      {data &&
        (data.enquiries.length === 0 ? (
          <EmptyState
            icon={Inbox}
            title={show === "open" ? "No open enquiries" : "Nothing closed yet"}
            hint="Quote requests from the provider website land here."
          />
        ) : (
          <ListCard>
            {data.enquiries.map((e, i) => (
              <Row
                key={e.id}
                initials={e.client_name}
                title={e.client_name}
                subtitle={`For ${e.patient_name ?? "a relative"}${e.area ? ` · ${e.area}` : ""} · ${ago(e.received_at)}`}
                trailing={
                  <StatusChip
                    status={e.status === "new" ? "open" : e.status}
                    label={label(e.status)}
                  />
                }
                onPress={() =>
                  router.push({ pathname: "/office/enquiry/[id]", params: { id: String(e.id) } })
                }
                last={i === data.enquiries.length - 1}
              />
            ))}
          </ListCard>
        ))}
    </Screen>
  );
}
