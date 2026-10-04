import { router, useLocalSearchParams } from "expo-router";
import { Alert, Button, Card, Spinner, Typography } from "heroui-native";
import type { JSX } from "react";
import { ScrollView, View } from "react-native";

import { SafeAreaView } from "@/components/SafeAreaView";
import { BackButton } from "@/components/ui/BackButton";
import type { OfficeConcernDetail } from "@/lib/api";
import { ago, clock } from "@/lib/format";
import { useOffice } from "@/lib/office";

export default function ConcernScreen(): JSX.Element {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { data: c, error } = useOffice<OfficeConcernDetail>(`/office/concerns/${id}`);

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top", "bottom"]}>
      <ScrollView contentContainerClassName="px-5 pb-12 gap-4">
        <View className="pt-2">
          <BackButton label="Concerns" />
        </View>

        {error && (
          <Alert status="danger">
            <Alert.Indicator />
            <Alert.Content>
              <Alert.Description>{error}</Alert.Description>
            </Alert.Content>
          </Alert>
        )}
        {!c && !error && (
          <View className="py-16 items-center">
            <Spinner size="lg" />
          </View>
        )}

        {c && (
          <>
            <View className="gap-1">
              <Typography.Heading type="h3">
                {c.plan_change ? "Care plan change request" : c.category}
              </Typography.Heading>
              <Typography color="muted">
                {c.client ?? "No client"} · from {c.raised_by} ({c.raised_by_role}) ·{" "}
                {ago(c.raised_at)}
              </Typography>
            </View>

            <Card>
              <Card.Header>
                <Card.Title>What was said</Card.Title>
              </Card.Header>
              <Card.Body>
                <Typography>{c.detail}</Typography>
              </Card.Body>
            </Card>

            <Card>
              <Card.Body className="gap-2">
                <Typography>
                  <Typography weight="bold">Status: </Typography>
                  {c.status === "investigating" ? "being looked into" : c.status}
                </Typography>
                <Typography>
                  <Typography weight="bold">Owner: </Typography>
                  {c.owner ?? "nobody yet"}
                </Typography>
                {c.shift && (
                  <Button
                    size="sm"
                    variant="outline"
                    className="self-start"
                    onPress={() =>
                      router.push({
                        pathname: "/office/shift/[id]",
                        params: { id: String(c.shift_id) },
                      })
                    }
                  >
                    {`Open the visit (${c.shift})`}
                  </Button>
                )}
                {c.resolution ? (
                  <Typography>
                    <Typography weight="bold">What was done: </Typography>
                    {c.resolution}
                    {c.resolved_at ? ` (${clock(c.resolved_at)})` : ""}
                  </Typography>
                ) : null}
              </Card.Body>
            </Card>

            <Typography type="body-xs" color="muted" align="center">
              To take ownership or resolve it, use Concerns in the portal on the website.
            </Typography>
          </>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}
