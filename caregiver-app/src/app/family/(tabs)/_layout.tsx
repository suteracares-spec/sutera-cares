import { Tabs } from "expo-router/js-tabs";
import { CalendarDays, MessageCircle, Receipt, UserRound } from "lucide-react-native";
import type { JSX } from "react";

import { tabIcon, useTabOptions } from "@/components/ui/TabBar";

export default function FamilyTabs(): JSX.Element {
  return (
    <Tabs screenOptions={useTabOptions()}>
      <Tabs.Screen name="index" options={{ title: "Visits", tabBarIcon: tabIcon(CalendarDays) }} />
      <Tabs.Screen name="invoices" options={{ title: "Invoices", tabBarIcon: tabIcon(Receipt) }} />
      <Tabs.Screen name="tell" options={{ title: "Tell us", tabBarIcon: tabIcon(MessageCircle) }} />
      <Tabs.Screen name="account" options={{ title: "Account", tabBarIcon: tabIcon(UserRound) }} />
    </Tabs>
  );
}
