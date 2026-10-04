import { Redirect, Stack } from "expo-router";
import type { JSX } from "react";

import { useAuth } from "@/lib/auth";
import { FamilyProvider } from "@/lib/family";

/** The family's area. Anyone else is sent back to the start. */
export default function FamilyLayout(): JSX.Element {
  const { token, profile } = useAuth();
  if (!token || profile?.password_change_required || (profile && profile.user.role !== "guardian")) {
    return <Redirect href="/" />;
  }
  return (
    <FamilyProvider>
      <Stack screenOptions={{ headerShown: false }} />
    </FamilyProvider>
  );
}
