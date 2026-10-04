import { HeartHandshake, Inbox, Receipt, ScrollText, UserCog } from "lucide-react-native";
import type { JSX } from "react";
import { router } from "expo-router";

import { AccountCard } from "@/components/AccountCard";
import { ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { StatusChip } from "@/components/ui/Status";
import type { Enquiry } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useOffice } from "@/lib/office";

export default function OfficeMore(): JSX.Element {
  const { profile } = useAuth();
  const isAdmin = profile?.user.role === "admin";
  const { data: enquiries } = useOffice<{ new_count: number; enquiries: Enquiry[] }>(
    "/office/enquiries?show=open"
  );

  return (
    <Screen title="More">
      <Section title="Office">
        <ListCard>
          <Row
            icon={Inbox}
            title="Enquiries"
            subtitle="Quote requests from the website"
            trailing={
              enquiries?.new_count ? (
                <StatusChip status="open" label={`${enquiries.new_count} new`} />
              ) : undefined
            }
            onPress={() => router.push("/office/enquiries")}
          />
          <Row
            icon={HeartHandshake}
            title="Caregivers"
            subtitle="Vetting, details and placements"
            onPress={() => router.push("/office/caregivers")}
          />
          <Row
            icon={Receipt}
            title="Invoices"
            subtitle="Bill a month, issue, share and record payments"
            onPress={() => router.push("/office/billing")}
            last
          />
        </ListCard>
      </Section>
      {isAdmin && (
        <Section title="Administration">
          <ListCard>
            <Row
              icon={UserCog}
              title="Staff"
              subtitle="Office accounts: add, suspend, reset two-factor"
              onPress={() => router.push("/office/staff")}
            />
            <Row
              icon={ScrollText}
              title="Audit log"
              subtitle="Who looked at what, and who changed what"
              onPress={() => router.push("/office/audit")}
              last
            />
          </ListCard>
        </Section>
      )}
      <AccountCard />
    </Screen>
  );
}
