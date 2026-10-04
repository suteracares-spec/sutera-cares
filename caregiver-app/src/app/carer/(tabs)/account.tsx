import type { JSX } from "react";

import { AccountCard } from "@/components/AccountCard";
import { SyncBanner } from "@/components/SyncBanner";
import { Screen } from "@/components/ui/Screen";

export default function CarerAccount(): JSX.Element {
  return (
    <Screen title="Account">
      <SyncBanner />
      <AccountCard />
    </Screen>
  );
}
