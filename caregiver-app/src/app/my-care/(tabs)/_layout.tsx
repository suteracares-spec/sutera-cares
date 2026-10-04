import { Tabs } from "expo-router/js-tabs";
import { CalendarDays, MessageCircle, UserRound } from "lucide-react-native";
import type { JSX } from "react";

import { tabIcon, useTabOptions } from "@/components/ui/TabBar";

export default function MyCareTabs(): JSX.Element {
  return (
    <Tabs screenOptions={useTabOptions()}>
      <Tabs.Screen name="index" options={{ title: "My visits", tabBarIcon: tabIcon(CalendarDays) }} />
      <Tabs.Screen name="tell" options={{ title: "Tell us", tabBarIcon: tabIcon(MessageCircle) }} />
      <Tabs.Screen name="account" options={{ title: "Account", tabBarIcon: tabIcon(UserRound) }} />
    </Tabs>
  );
}
