import { Send } from "lucide-react-native";
import { Button, Card, Typography, useThemeColor } from "heroui-native";
import { Share } from "react-native";

import { PORTAL_URL } from "@/components/AccountCard";
import type { Credentials } from "@/lib/api";

/**
 * A temporary password, shown once: it is stored only as a hash, so it
 * cannot be shown again. Shared with the person by WhatsApp or SMS from
 * the phone's own share sheet.
 */
export function CredentialsCard({ creds, onDone }: { creds: Credentials; onDone: () => void }) {
  const accentFg = useThemeColor("accent-foreground");

  const share = () =>
    Share.share({
      message:
        `Your Sutera Care sign-in:\n` +
        `Email: ${creds.email}\n` +
        `Temporary password: ${creds.password}\n\n` +
        `Sign in at ${PORTAL_URL} or in the Sutera Care app. ` +
        `You will be asked to choose your own password.`,
    });

  return (
    <Card className="border border-warning-soft bg-warning-soft">
      <Card.Body className="gap-2">
        <Typography weight="semibold">Temporary password for {creds.name}</Typography>
        <Typography type="body-sm" color="muted">
          {creds.email}
        </Typography>
        <Typography selectable className="text-2xl font-bold tracking-widest my-1">
          {creds.password}
        </Typography>
        <Typography type="body-sm" color="muted">
          Shown only once. They choose their own password when they first sign in.
          {creds.suspended
            ? " Their sign-in is suspended, so it works only once access is restored."
            : ""}
        </Typography>
        <Button onPress={share}>
          <Send size={18} color={accentFg} />
          <Button.Label>Send by WhatsApp or SMS</Button.Label>
        </Button>
        <Button variant="ghost" onPress={onDone}>
          Done
        </Button>
      </Card.Body>
    </Card>
  );
}
