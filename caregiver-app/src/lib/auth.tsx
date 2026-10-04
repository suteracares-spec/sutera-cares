import AsyncStorage from "@react-native-async-storage/async-storage";
import * as Application from "expo-application";
import * as SecureStore from "expo-secure-store";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { Platform } from "react-native";

import { ApiError, request, type Profile } from "./api";
import { DETAILS_KEY, LISTS_KEY, PROFILE_KEY, TOKEN_KEY } from "./keys";

// The token lives in Android's encrypted keystore (SecureStore), never in
// plain storage.

type Auth = {
  ready: boolean;
  token: string | null;
  profile: Profile | null;
  signIn: (email: string, password: string) => Promise<void>;
  choosePassword: (password: string, confirmation: string) => Promise<void>;
  signOut: () => Promise<void>;
  /** The server said the token is no longer good (signed out elsewhere, suspended). */
  expired: () => Promise<void>;
};

const AuthContext = createContext<Auth | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [ready, setReady] = useState(false);
  const [token, setToken] = useState<string | null>(null);
  const [profile, setProfile] = useState<Profile | null>(null);

  const save = async (t: string, p: Profile) => {
    await SecureStore.setItemAsync(TOKEN_KEY, t);
    await SecureStore.setItemAsync(PROFILE_KEY, JSON.stringify(p));
    setToken(t);
    setProfile(p);
  };

  const clear = useCallback(async () => {
    // Cached shifts describe clients: they go when the caregiver signs out.
    // Actions still waiting to be sent stay; they belong to the visits.
    await AsyncStorage.multiRemove([LISTS_KEY, DETAILS_KEY]);
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await SecureStore.deleteItemAsync(PROFILE_KEY);
    setToken(null);
    setProfile(null);
  }, []);

  useEffect(() => {
    (async () => {
      // Whatever happens reading storage, the app must not sit on a
      // spinner: an unreadable token just means signing in again.
      let t: string | null = null;
      let p: string | null = null;
      try {
        [t, p] = await Promise.all([
          SecureStore.getItemAsync(TOKEN_KEY),
          SecureStore.getItemAsync(PROFILE_KEY),
        ]);
      } catch (e) {
        console.warn("Could not read the stored sign-in", e);
      }
      setToken(t);
      setProfile(p ? (JSON.parse(p) as Profile) : null);
      setReady(true);

      // Refresh the profile when there is signal; keep the stored one when not.
      if (t) {
        try {
          const fresh = await request<Profile>("/me", { token: t });
          setProfile(fresh);
          await SecureStore.setItemAsync(PROFILE_KEY, JSON.stringify(fresh));
        } catch (e) {
          // Offline or a server hiccup: carry on with the stored profile.
          if (e instanceof ApiError && e.status === 401) {
            await clear();
          }
        }
      }
    })();
  }, [clear]);

  const value = useMemo<Auth>(
    () => ({
      ready,
      token,
      profile,
      async signIn(email, password) {
        const device = `${Application.applicationName ?? "Sutera"} on ${Platform.OS} ${Platform.Version}`;
        const result = await request<Profile & { token: string }>("/login", {
          method: "POST",
          body: { email: email.trim(), password, device },
        });
        const { token: t, ...p } = result;
        await save(t, p);
      },
      async choosePassword(password, confirmation) {
        const p = await request<Profile>("/password", {
          method: "POST",
          token,
          body: { password, password_confirmation: confirmation },
        });
        await save(token!, p);
      },
      async signOut() {
        try {
          await request("/logout", { method: "POST", token });
        } catch {
          // Signing out works offline too: the token is forgotten here, and
          // expires on the server in time.
        }
        await clear();
      },
      expired: clear,
    }),
    [ready, token, profile, clear]
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): Auth {
  const auth = useContext(AuthContext);
  if (!auth) throw new Error("useAuth outside AuthProvider");
  return auth;
}
