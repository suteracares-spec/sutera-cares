import { ChevronRight, type LucideIcon } from "lucide-react-native";
import { Card, Typography, useThemeColor } from "heroui-native";
import type { ReactNode } from "react";
import { Pressable, View } from "react-native";

/** A small caps heading above a group of rows. */
export function Section({
  title,
  action,
  children,
}: {
  title: string;
  action?: ReactNode;
  children: ReactNode;
}) {
  return (
    <View className="gap-2">
      <View className="flex-row items-center justify-between mt-2">
        <Typography
          type="body-xs"
          weight="bold"
          color="muted"
          className="uppercase tracking-widest"
        >
          {title}
        </Typography>
        {action}
      </View>
      {children}
    </View>
  );
}

/** A card holding rows, separated by hairlines. */
export function ListCard({ children }: { children: ReactNode }) {
  return (
    <Card className="p-0 overflow-hidden">
      <View>{children}</View>
    </Card>
  );
}

/** Initials in a coloured circle: a person or client at a glance. */
export function Initials({ name, size = 40 }: { name: string; size?: number }) {
  const letters = name
    .replace(/^(Puan|Encik|Mr|Mrs|Ms|Mdm|Dr|Cik)\.?\s+/i, "")
    .split(/\s+/)
    .filter((w) => !/^(binti|bin|a\/l|a\/p)$/i.test(w))
    .slice(0, 2)
    .map((w) => w[0]?.toUpperCase() ?? "")
    .join("");
  return (
    <View
      className="bg-accent-soft items-center justify-center rounded-full"
      style={{ width: size, height: size }}
    >
      <Typography
        weight="bold"
        className="text-accent-soft-foreground"
        style={{ fontSize: Math.round(size * 0.38), lineHeight: Math.round(size * 0.5) }}
      >
        {letters || "?"}
      </Typography>
    </View>
  );
}

/** One row: leading icon or initials, title, subtitle, trailing chip or chevron. */
export function Row({
  title,
  subtitle,
  icon: Icon,
  initials,
  trailing,
  onPress,
  last,
  danger,
}: {
  title: string;
  subtitle?: string | null;
  icon?: LucideIcon;
  initials?: string;
  trailing?: ReactNode;
  onPress?: () => void;
  last?: boolean;
  danger?: boolean;
}) {
  const [muted, dangerColor] = useThemeColor(["muted", "danger"]);
  const body = (
    <View
      className={`flex-row items-center gap-3 px-4 py-3 ${last ? "" : "border-b border-separator"}`}
    >
      {initials ? <Initials name={initials} /> : null}
      {Icon ? <Icon size={22} color={danger ? dangerColor : muted} /> : null}
      <View className="flex-1">
        <Typography weight="semibold" className={danger ? "text-danger" : undefined}>
          {title}
        </Typography>
        {subtitle ? (
          <Typography type="body-sm" color="muted">
            {subtitle}
          </Typography>
        ) : null}
      </View>
      {trailing}
      {onPress && !trailing ? <ChevronRight size={20} color={muted} /> : null}
    </View>
  );

  return onPress ? (
    <Pressable onPress={onPress} accessibilityRole="button" accessibilityLabel={title}>
      {body}
    </Pressable>
  ) : (
    body
  );
}

/** Nothing to show: an icon, a line saying so, and optionally what to do. */
export function EmptyState({
  icon: Icon,
  title,
  hint,
}: {
  icon: LucideIcon;
  title: string;
  hint?: string;
}) {
  const muted = useThemeColor("muted");
  return (
    <View className="items-center gap-2 py-10 px-6">
      <Icon size={36} color={muted} />
      <Typography weight="semibold" align="center">
        {title}
      </Typography>
      {hint ? (
        <Typography type="body-sm" color="muted" align="center">
          {hint}
        </Typography>
      ) : null}
    </View>
  );
}
