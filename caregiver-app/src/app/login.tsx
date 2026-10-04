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
import { KeyboardAvoidingView, Platform, ScrollView, View } from "react-native";
import { SafeAreaView } from "@/components/SafeAreaView";

import { ApiError, OfflineError } from "@/lib/api";
import { useAuth } from "@/lib/auth";

export default function LoginScreen(): JSX.Element {
  const { token, signIn } = useAuth();
  const accentForeground = useThemeColor("accent-foreground");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  if (token) return <Redirect href="/" />;

  const submit = async () => {
    if (!email || !password) {
      setError("Enter your email and password.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await signIn(email, password);
    } catch (e) {
      setError(
        e instanceof ApiError
          ? e.firstMessage
          : e instanceof OfflineError
            ? "No connection. Signing in needs signal the first time."
            : "Something went wrong. Try again."
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <SafeAreaView className="flex-1 bg-background">
      <KeyboardAvoidingView
        className="flex-1"
        behavior={Platform.OS === "ios" ? "padding" : undefined}
      >
        <ScrollView
          contentContainerClassName="flex-grow justify-center px-6 py-10"
          keyboardShouldPersistTaps="handled"
        >
          <View className="items-center mb-8 gap-1">
            <Typography.Heading type="h2">Sutera Care Provider</Typography.Heading>
            <Typography color="muted">For caregivers</Typography>
          </View>

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
                <Label>Email</Label>
                <Input
                  variant="secondary"
                  value={email}
                  onChangeText={setEmail}
                  autoCapitalize="none"
                  autoComplete="email"
                  keyboardType="email-address"
                  textContentType="username"
                  placeholder="you@example.com"
                />
              </TextField>

              <TextField isRequired>
                <Label>Password</Label>
                <Input
                  variant="secondary"
                  value={password}
                  onChangeText={setPassword}
                  secureTextEntry
                  autoComplete="password"
                  textContentType="password"
                  onSubmitEditing={submit}
                />
                <Description>
                  First time? Use the temporary password the office gave you.
                </Description>
              </TextField>

              <Button size="lg" onPress={submit} isDisabled={busy}>
                {busy ? <Spinner size="sm" color={accentForeground} /> : "Sign in"}
              </Button>
            </Card.Body>
          </Card>

          <Typography type="body-xs" color="muted" align="center" className="mt-6">
            This app shows client information. Keep your phone locked.
          </Typography>
        </ScrollView>
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}
