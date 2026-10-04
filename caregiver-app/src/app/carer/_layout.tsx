import { Redirect, Stack } from "expo-router";
import type { JSX } from "react";

import { isFamily } from "@/lib/api";
import { useAuth } from "@/lib/auth";

/** The caregiver's area. Anyone else is sent back to the start. */
export default function CarerLayout(): JSX.Element {
  const { token, profile } = useAuth();
  if (!token || profile?.password_change_required || profile?.user.office || isFamily(profile?.user.role)) {
    return <Redirect href="/" />;
  }
  return <Stack screenOptions={{ headerShown: false }} />;
}
