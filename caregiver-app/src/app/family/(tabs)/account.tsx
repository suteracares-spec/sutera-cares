import type { JSX } from "react";

import { AccountCard } from "@/components/AccountCard";
import { Screen } from "@/components/ui/Screen";

export default function FamilyAccount(): JSX.Element {
  return (
    <Screen title="Account">
      <AccountCard />
    </Screen>
  );
}
