import { Typography } from "heroui-native";
import type { ReactNode } from "react";
import { RefreshControl, ScrollView, View } from "react-native";

import { SafeAreaView } from "@/components/SafeAreaView";

import { BackButton } from "./BackButton";

/**
 * A page: safe area, large title, optional subtitle and header action, and
 * a scrolling body with pull-to-refresh. Tab screens leave out `back`;
 * pushed screens pass the label of where back goes.
 */
export function Screen({
  title,
  subtitle,
  action,
  back,
  refreshing,
  onRefresh,
  children,
  scroll = true,
}: {
  title?: string;
  subtitle?: string;
  action?: ReactNode;
  back?: string;
  refreshing?: boolean;
  onRefresh?: () => void;
  children: ReactNode;
  scroll?: boolean;
}) {
  const header = (title || back) && (
    <View className="gap-1 pt-2">
      {back ? <BackButton label={back} /> : null}
      {title ? (
        <View className="flex-row items-end justify-between gap-3">
          <View className="flex-1">
            <Typography.Heading type="h2">{title}</Typography.Heading>
            {subtitle ? <Typography color="muted">{subtitle}</Typography> : null}
          </View>
          {action}
        </View>
      ) : null}
    </View>
  );

  return (
    <SafeAreaView className="flex-1 bg-background" edges={["top"]}>
      {scroll ? (
        <ScrollView
          contentContainerClassName="px-5 pb-12 gap-4"
          keyboardShouldPersistTaps="handled"
          refreshControl={
            onRefresh ? (
              <RefreshControl refreshing={!!refreshing} onRefresh={onRefresh} />
            ) : undefined
          }
        >
          {header}
          {children}
        </ScrollView>
      ) : (
        <View className="flex-1 px-5 gap-4">
          {header}
          {children}
        </View>
      )}
    </SafeAreaView>
  );
}
