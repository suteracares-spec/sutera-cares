import {
  HeartHandshake,
  Receipt,
  ScrollText,
  Inbox,
  UserCog,
  type LucideIcon,
} from "lucide-react-native";
import type { JSX } from "react";
import { router } from "expo-router";
import { Linking } from "react-native";

import { AccountCard, PORTAL_URL } from "@/components/AccountCard";
import { ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { StatusChip } from "@/components/ui/Status";
import type { Enquiry } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { useOffice } from "@/lib/office";

/** Portal areas still on the website: each moves into the app in its phase. */
const ON_WEBSITE: { icon: LucideIcon; title: string; path: string; adminOnly?: boolean }[] = [
  { icon: Receipt, title: "Invoices", path: "/admin/invoices" },
  { icon: UserCog, title: "Staff", path: "/admin/staff", adminOnly: true },
  { icon: ScrollText, title: "Audit log", path: "/admin/audit", adminOnly: true },
];

export default function OfficeMore(): JSX.Element {
  const { profile } = useAuth();
  const isAdmin = profile?.user.role === "admin";
  const items = ON_WEBSITE.filter((i) => !i.adminOnly || isAdmin);
  const { data: enquiries } = useOffice<{ new_count: number; enquiries: Enquiry[] }>(
    "/office/enquiries?show=open"
  );

  return (
    <Screen title="More">
      <Section title="In the app">
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
            last
          />
        </ListCard>
      </Section>
      <Section title="On the website for now">
        <ListCard>
          {items.map((i, n) => (
            <Row
              key={i.title}
              icon={i.icon}
              title={i.title}
              subtitle="Opens the portal"
              onPress={() => Linking.openURL(PORTAL_URL + i.path)}
              last={n === items.length - 1}
            />
          ))}
        </ListCard>
      </Section>
      <AccountCard />
    </Screen>
  );
}
