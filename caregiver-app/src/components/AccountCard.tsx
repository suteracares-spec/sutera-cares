import * as Application from "expo-application";
import { Button, Card, Typography, useThemeColor } from "heroui-native";
import { Globe, KeyRound, LogOut } from "lucide-react-native";
import { Linking, View } from "react-native";

import { Initials, ListCard, Row, Section } from "@/components/ui/List";
import { useAuth } from "@/lib/auth";

export const PORTAL_URL = "https://providers.suteracares.org/portal";

const ROLE: Record<string, string> = {
  admin: "Administrator",
  coordinator: "Coordinator",
  caregiver: "Caregiver",
};

/** Who is signed in, and the ways out. */
export function AccountCard() {
  const { profile, signOut } = useAuth();
  if (!profile) return null;
  const { user } = profile;

  return (
    <>
      <Card>
        <Card.Body className="flex-row items-center gap-4">
          <Initials name={user.name} size={56} />
          <View className="flex-1">
            <Typography.Heading type="h5">{user.name}</Typography.Heading>
            <Typography color="muted">{user.email}</Typography>
            <Typography type="body-sm" color="muted">
              {ROLE[user.role ?? "caregiver"] ?? user.role}
              {user.code ? ` · ${user.code}` : ""}
            </Typography>
          </View>
        </Card.Body>
      </Card>

      <Section title="Account">
        <ListCard>
          <Row
            icon={KeyRound}
            title="Change password"
            subtitle="On the website, under your name"
            onPress={() => Linking.openURL(`${PORTAL_URL}/account`)}
          />
          <Row
            icon={Globe}
            title="Open the portal website"
            subtitle="providers.suteracares.org/portal"
            onPress={() => Linking.openURL(PORTAL_URL)}
            last
          />
        </ListCard>
      </Section>

      <Button variant="danger-soft" onPress={signOut}>
        <LogOutIcon />
        <Button.Label>Sign out</Button.Label>
      </Button>

      <Typography type="body-xs" color="muted" align="center">
        Sutera Care {Application.nativeApplicationVersion ?? ""}
      </Typography>
    </>
  );
}

function LogOutIcon() {
  return <LogOut size={18} color={useThemeColor("danger")} />;
}
