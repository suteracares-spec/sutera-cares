import { router } from "expo-router";
import { ChevronLeft } from "lucide-react-native";
import { Typography, useThemeColor } from "heroui-native";
import { Pressable } from "react-native";

export function BackButton({ label }: { label: string }) {
  const accent = useThemeColor("accent");
  return (
    <Pressable
      onPress={() => router.back()}
      accessibilityRole="button"
      accessibilityLabel={`Back to ${label}`}
      hitSlop={12}
      className="flex-row items-center self-start py-2 -ml-1"
    >
      <ChevronLeft size={22} color={accent} />
      <Typography weight="semibold" className="text-accent">
        {label}
      </Typography>
    </Pressable>
  );
}
