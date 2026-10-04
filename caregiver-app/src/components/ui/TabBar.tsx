import type { LucideIcon } from "lucide-react-native";
import { useThemeColor } from "heroui-native";
import type { ColorValue } from "react-native";

/** Shared look for every role's bottom tabs. */
export function useTabOptions() {
  const [accent, muted, surface, separator] = useThemeColor([
    "accent",
    "muted",
    "surface",
    "separator",
  ]);
  return {
    headerShown: false,
    tabBarActiveTintColor: accent,
    tabBarInactiveTintColor: muted,
    tabBarStyle: { backgroundColor: surface, borderTopColor: separator },
    tabBarLabelStyle: { fontSize: 12, fontWeight: "600" as const },
  };
}

/** A Lucide icon as a tab icon. */
export function tabIcon(Icon: LucideIcon) {
  function TabIcon({ color, size }: { color: ColorValue; size: number }) {
    return <Icon color={String(color)} size={size} />;
  }
  return TabIcon;
}
