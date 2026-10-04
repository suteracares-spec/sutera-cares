import type { JSX } from "react";

import { AccountCard } from "@/components/AccountCard";
import { Screen } from "@/components/ui/Screen";

export default function MyAccount(): JSX.Element {
  return (
    <Screen title="Account">
      <AccountCard />
    </Screen>
  );
}
