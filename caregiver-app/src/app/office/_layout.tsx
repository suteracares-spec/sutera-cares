import { Redirect, Stack } from "expo-router";
import type { JSX } from "react";

import { useAuth } from "@/lib/auth";

/** The office area, for administrators and coordinators only. */
export default function OfficeLayout(): JSX.Element {
  const { token, profile } = useAuth();
  if (!token || profile?.password_change_required || (profile && !profile.user.office)) {
    return <Redirect href="/" />;
  }
  return <Stack screenOptions={{ headerShown: false }} />;
}
