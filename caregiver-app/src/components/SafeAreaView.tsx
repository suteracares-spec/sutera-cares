import { SafeAreaView as RNSafeAreaView } from "react-native-safe-area-context";
import { withUniwind } from "uniwind";

/**
 * SafeAreaView that understands className. Uniwind styles React Native's
 * own components automatically, but a third-party one like this must be
 * wrapped, or its className is silently ignored, and a screen given
 * "flex-1" collapses to the height of the status bar.
 */
export const SafeAreaView = withUniwind(RNSafeAreaView);
