import {
  CalendarClock,
  HeartHandshake,
  Inbox,
  Receipt,
  ScrollText,
  UserCog,
  Users,
  type LucideIcon,
} from "lucide-react-native";
import type { JSX } from "react";
import { Linking } from "react-native";

import { AccountCard, PORTAL_URL } from "@/components/AccountCard";
import { ListCard, Row, Section } from "@/components/ui/List";
import { Screen } from "@/components/ui/Screen";
import { useAuth } from "@/lib/auth";

/** Portal areas still on the website: each moves into the app in its phase. */
const ON_WEBSITE: { icon: LucideIcon; title: string; path: string; adminOnly?: boolean }[] = [
  { icon: CalendarClock, title: "Schedule", path: "/admin/schedule" },
  { icon: Users, title: "Clients", path: "/admin/patients" },
  { icon: HeartHandshake, title: "Caregivers", path: "/admin/caregivers" },
  { icon: Inbox, title: "Enquiries", path: "/admin/enquiries" },
  { icon: Receipt, title: "Invoices", path: "/admin/invoices" },
  { icon: UserCog, title: "Staff", path: "/admin/staff", adminOnly: true },
  { icon: ScrollText, title: "Audit log", path: "/admin/audit", adminOnly: true },
];

export default function OfficeMore(): JSX.Element {
  const { profile } = useAuth();
  const isAdmin = profile?.user.role === "admin";
  const items = ON_WEBSITE.filter((i) => !i.adminOnly || isAdmin);

  return (
    <Screen title="More">
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
