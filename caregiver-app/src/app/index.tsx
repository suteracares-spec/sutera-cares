import { Redirect } from "expo-router";
import { Spinner } from "heroui-native";
import type { JSX } from "react";
import { View } from "react-native";

import { useAuth } from "@/lib/auth";

/** Where the app opens: each kind of account goes to its own area. */
export default function Start(): JSX.Element {
  const { ready, token, profile } = useAuth();

  if (!ready) {
    return (
      <View className="flex-1 bg-background items-center justify-center">
        <Spinner size="lg" />
      </View>
    );
  }
  if (!token) return <Redirect href="/login" />;
  if (profile?.password_change_required) return <Redirect href="/password" />;
  if (profile?.user.office) return <Redirect href="/office" />;
  if (profile?.user.role === "guardian") return <Redirect href="/family" />;
  if (profile?.user.role === "patient") return <Redirect href="/my-care" />;
  return <Redirect href="/carer" />;
}
