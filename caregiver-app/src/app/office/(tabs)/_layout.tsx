import { Tabs } from "expo-router/js-tabs";
import { CalendarDays, Ellipsis, House, MessageCircleWarning, Users } from "lucide-react-native";
import type { JSX } from "react";

import { tabIcon, useTabOptions } from "@/components/ui/TabBar";

export default function OfficeTabs(): JSX.Element {
  return (
    <Tabs screenOptions={useTabOptions()}>
      <Tabs.Screen name="index" options={{ title: "Today", tabBarIcon: tabIcon(House) }} />
      <Tabs.Screen
        name="schedule"
        options={{ title: "Schedule", tabBarIcon: tabIcon(CalendarDays) }}
      />
      <Tabs.Screen name="clients" options={{ title: "Clients", tabBarIcon: tabIcon(Users) }} />
      <Tabs.Screen
        name="concerns"
        options={{ title: "Concerns", tabBarIcon: tabIcon(MessageCircleWarning) }}
      />
      <Tabs.Screen name="more" options={{ title: "More", tabBarIcon: tabIcon(Ellipsis) }} />
    </Tabs>
  );
}
