import { router } from "expo-router";
import { Alert, Button, Card, Chip, Spinner, Typography } from "heroui-native";
import { useState, type JSX } from "react";
import { Pressable, RefreshControl, ScrollView, View } from "react-native";

import { SafeAreaView } from "@/components/SafeAreaView";
import type { OfficeConcern } from "@/lib/api";
import { ago } from "@/lib/format";
import { useOffice } from "@/lib/office";

export default function ConcernsScreen(): JSX.Element {
  const [show, setShow] = useState<"open" | "closed">("open");
  const { data, error, loading, reload } = useOffice<{ concerns: OfficeConcern[] }>(
    `/office/concerns?show=${show}`
  );

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top"]}>
      <ScrollView
        contentContainerClassName="px-5 pb-10 gap-3"
        refreshControl={<RefreshControl refreshing={loading && !!data} onRefresh={reload} />}
      >
        <Button variant="ghost" size="sm" className="self-start mt-2" onPress={() => router.back()}>
          ← Office
        </Button>
        <Typography.Heading type="h2">Concerns</Typography.Heading>

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
            Resolved
          </Button>
        </View>

        {error && (
          <Alert status="danger">
            <Alert.Indicator />
            <Alert.Content>
              <Alert.Description>{error} Pull down to try again.</Alert.Description>
            </Alert.Content>
          </Alert>
        )}
        {!data && loading && (
          <View className="py-16 items-center">
            <Spinner size="lg" />
          </View>
        )}
        {data && data.concerns.length === 0 && (
          <Typography color="muted" align="center" className="mt-8">
            {show === "open" ? "Nothing open." : "Nothing resolved yet."}
          </Typography>
        )}

        {data?.concerns.map((c) => (
          <Pressable
            key={c.id}
            onPress={() =>
              router.push({ pathname: "/office/concern/[id]", params: { id: String(c.id) } })
            }
            accessibilityRole="button"
          >
            <Card>
              <Card.Body className="gap-1">
                <Typography type="h6" weight="bold">
                  {c.plan_change ? "Care plan change request" : c.category}
                </Typography>
                <Typography>{c.client ?? "No client"}</Typography>
                <Typography type="body-sm" color="muted">
                  From {c.raised_by} ({c.raised_by_role}) · {ago(c.raised_at)}
                </Typography>
              </Card.Body>
              <Card.Footer className="flex-row gap-2">
                <Chip
                  variant="soft"
                  size="sm"
                  color={
                    c.status === "open"
                      ? "danger"
                      : c.status === "investigating"
                        ? "warning"
                        : "success"
                  }
                >
                  <Chip.Label>
                    {c.status === "investigating" ? "Being looked into" : c.status}
                  </Chip.Label>
                </Chip>
                <Chip variant="soft" size="sm" color={c.owner ? "default" : "danger"}>
                  <Chip.Label>{c.owner ? c.owner : "No owner"}</Chip.Label>
                </Chip>
              </Card.Footer>
            </Card>
          </Pressable>
        ))}
      </ScrollView>
    </SafeAreaView>
  );
}
