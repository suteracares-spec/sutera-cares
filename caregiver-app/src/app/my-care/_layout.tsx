import { Redirect, Stack } from "expo-router";
import type { JSX } from "react";

import { useAuth } from "@/lib/auth";

/** The client's own area. Anyone else is sent back to the start. */
export default function MyCareLayout(): JSX.Element {
  const { token, profile } = useAuth();
  if (!token || profile?.password_change_required || (profile && profile.user.role !== "patient")) {
    return <Redirect href="/" />;
  }
  return <Stack screenOptions={{ headerShown: false }} />;
}
