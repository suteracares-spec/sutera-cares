import { Redirect } from "expo-router";
import {
  Alert,
  Button,
  Card,
  Description,
  Input,
  Label,
  Spinner,
  TextField,
  Typography,
  useThemeColor,
} from "heroui-native";
import { useState, type JSX } from "react";
import { ScrollView } from "react-native";
import { SafeAreaView } from "@/components/SafeAreaView";

import { ApiError, OfflineError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { firstName } from "@/lib/format";

/** First sign-in: replace the office's temporary password with your own. */
export default function PasswordScreen(): JSX.Element {
  const { token, profile, choosePassword, signOut } = useAuth();
  const accentForeground = useThemeColor("accent-foreground");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (!token) return <Redirect href="/login" />;
  if (profile && !profile.password_change_required) return <Redirect href="/" />;

  const submit = async () => {
    if (password.length < 12) return setError("At least 12 characters.");
    if (password !== confirmation) return setError("The two passwords do not match.");
    setBusy(true);
    setError(null);
    try {
      await choosePassword(password, confirmation);
    } catch (e) {
      setError(
        e instanceof ApiError
          ? e.firstMessage
          : e instanceof OfflineError
            ? e.message
            : "Try again."
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <SafeAreaView className="flex-1 bg-background">
      <ScrollView
        contentContainerClassName="flex-grow justify-center px-6 py-10"
        keyboardShouldPersistTaps="handled"
      >
        <Typography.Heading type="h3" className="mb-1">
          Welcome{profile ? `, ${firstName(profile.user.name)}` : ""}
        </Typography.Heading>
        <Typography color="muted" className="mb-6">
          Choose your own password to replace the temporary one.
        </Typography>

        <Card>
          <Card.Body className="gap-4">
            {error && (
              <Alert status="danger">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Description>{error}</Alert.Description>
                </Alert.Content>
              </Alert>
            )}
            <TextField isRequired>
              <Label>New password</Label>
              <Input
                variant="secondary"
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                autoComplete="new-password"
              />
              <Description>
                At least 12 characters. Three or four unrelated words is strong and easy to
                remember.
              </Description>
            </TextField>
            <TextField isRequired>
              <Label>Repeat it</Label>
              <Input
                variant="secondary"
                value={confirmation}
                onChangeText={setConfirmation}
                secureTextEntry
                onSubmitEditing={submit}
              />
            </TextField>
            <Button size="lg" onPress={submit} isDisabled={busy}>
              {busy ? <Spinner size="sm" color={accentForeground} /> : "Set password"}
            </Button>
            <Button variant="ghost" onPress={signOut}>
              Sign out
            </Button>
          </Card.Body>
        </Card>
      </ScrollView>
    </SafeAreaView>
  );
}
