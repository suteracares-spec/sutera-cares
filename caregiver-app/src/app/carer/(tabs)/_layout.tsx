import { Tabs } from "expo-router/js-tabs";
import { CalendarDays, UserRound } from "lucide-react-native";
import type { JSX } from "react";

import { tabIcon, useTabOptions } from "@/components/ui/TabBar";

export default function CarerTabs(): JSX.Element {
  return (
    <Tabs screenOptions={useTabOptions()}>
      <Tabs.Screen
        name="index"
        options={{ title: "My shifts", tabBarIcon: tabIcon(CalendarDays) }}
      />
      <Tabs.Screen name="account" options={{ title: "Account", tabBarIcon: tabIcon(UserRound) }} />
    </Tabs>
  );
}
