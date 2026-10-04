// Talks to the portal's caregiver API (/portal/api/v1). Every request
// carries the phone's sign-in token; nothing else identifies the user.

// The live portal, unless a build says otherwise (a local test portal is
// reached from the Android emulator at http://10.0.2.2:<port>/api/v1).
export const API_BASE =
  process.env.EXPO_PUBLIC_API_URL ?? "https://providers.suteracares.org/portal/api/v1";

export type Shift = {
  id: number;
  date: string; // YYYY-MM-DD
  start: string; // HH:MM
  end: string;
  status: "scheduled" | "in_progress" | "completed" | "missed" | "cancelled";
  client: { name: string; area: string | null };
  service: string | null;
};

export type ShiftDetail = Shift & {
  cancel_reason: string | null;
  check_in_problem: string | null;
  opens_at: string;
  client_details: {
    address: string | null;
    postcode: string | null;
    mobility: string | null;
    languages: string | null;
    allergies: string | null;
  };
  plan: {
    notes: string | null;
    tasks: { id: number; description: string; category: string; when: string }[];
  } | null;
  visit: {
    check_in_at: string | null;
    check_out_at: string | null;
    tasks_completed: string[];
    notes: string | null;
    concern_flagged: boolean;
  } | null;
  already?: boolean;
};

export type ShiftLists = {
  today: Shift[];
  unfinished: Shift[];
  coming: Shift[];
  server_time: string;
};

export type Profile = {
  user: {
    name: string;
    email: string;
    code: string | null;
    status: string;
    role?: string;
    /** An office account (administrator or coordinator): sees the office overview. */
    office?: boolean;
  };
  password_change_required: boolean;
};

// ---- Office overview (administrators and coordinators) ----

export type OfficeShift = {
  id: number;
  date: string;
  start: string;
  end: string;
  status: Shift["status"];
  /** Needs a coordinator now: not checked in after the start, or nobody came. */
  attention: "late" | "no_show" | null;
  client: string | null;
  area: string | null;
  caregiver: string | null;
  covering: boolean;
  service: string | null;
  check_in_at: string | null;
  concern: boolean;
};

export type OfficeDay = {
  date: string;
  counts: { total: number; attention: number; on_now: number; done: number };
  open_concerns: number;
  shifts: OfficeShift[];
};

export type OfficeShiftDetail = OfficeShift & {
  cancel_reason: string | null;
  client_details: {
    name: string;
    code: string;
    area: string | null;
    address: string | null;
    allergies: string | null;
  };
  visit: {
    check_in_at: string | null;
    check_out_at: string | null;
    location: boolean;
    minutes_worked: number | null;
    tasks_completed: string[];
    notes: string | null;
    concern_flagged: boolean;
    concern_detail: string | null;
  } | null;
};

export type OfficeConcern = {
  id: number;
  status: "open" | "investigating" | "resolved" | "closed";
  category: string;
  plan_change: boolean;
  client: string | null;
  raised_by: string | null;
  raised_by_role: string | null;
  owner: string | null;
  owner_id: number | null;
  raised_at: string;
};

export type OfficeConcernDetail = OfficeConcern & {
  detail: string;
  resolution: string | null;
  resolved_at: string | null;
  shift_id: number | null;
  shift: string | null;
};

/** The server answered with a refusal (4xx). Not worth retrying as-is. */
export class ApiError extends Error {
  constructor(
    public status: number,
    message: string,
    public fields: Record<string, string[]> = {}
  ) {
    super(message);
  }

  /** The first field error, or the general message: what to show a person. */
  get firstMessage(): string {
    const first = Object.values(this.fields)[0]?.[0];
    return first ?? this.message;
  }
}

/** The phone could not reach the server at all. Worth retrying later. */
export class OfflineError extends Error {}

export async function request<T>(
  path: string,
  options: {
    method?: "GET" | "POST" | "PUT" | "DELETE";
    body?: unknown;
    token?: string | null;
  } = {}
): Promise<T> {
  let response: Response;
  try {
    response = await fetch(API_BASE + path, {
      method: options.method ?? "GET",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
      },
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
    });
  } catch {
    throw new OfflineError("No connection to the office.");
  }

  const data = await response.json().catch(() => ({}));

  if (response.ok) {
    return data as T;
  }
  if (response.status >= 500) {
    // The server is having trouble: treat like no signal, and retry later.
    throw new OfflineError("The office system is not responding. Try again shortly.");
  }
  throw new ApiError(response.status, data.message ?? "Something went wrong.", data.errors ?? {});
}

export type OfficeWeek = {
  week: string;
  days: string[];
  shifts: OfficeShift[];
  caregivers: { id: number; name: string | null }[];
  clients: { id: number; name: string }[];
};

export type ReliefOption = { id: number; name: string | null; code: string; area: string | null };

export type StaffMember = { id: number; name: string };

export type EnquiryStatus =
  "new" | "contacted" | "assessment_booked" | "converted" | "declined" | "lost";

export type Enquiry = {
  id: number;
  status: EnquiryStatus;
  client_name: string;
  patient_name: string | null;
  area: string | null;
  received_at: string;
  converted: boolean;
};

export type EnquiryDetail = Enquiry & {
  client_phone: string;
  client_email: string | null;
  client_relationship: string | null;
  patient_age: number | null;
  patient_mobility: string | null;
  needs: string | null;
  schedule_wanted: string | null;
  patient_id: number | null;
  statuses: EnquiryStatus[];
};

/** How an enquiry's status reads on screen. */
export function enquiryLabel(status: EnquiryStatus): string {
  return {
    new: "New",
    contacted: "Contacted",
    assessment_booked: "Assessment booked",
    converted: "Now a client",
    declined: "Declined",
    lost: "Lost",
  }[status];
}

// ---- Clients (phase 3) ----

export type ClientStatus = "enquiry" | "assessment" | "active" | "paused" | "closed";

export type ClientSummary = {
  id: number;
  code: string;
  name: string;
  area: string | null;
  status: ClientStatus;
  consent: boolean;
};

export type FamilyLink = {
  id: number;
  user_id: number;
  name: string | null;
  email: string | null;
  phone: string | null;
  login_status: string | null;
  relationship: string | null;
  is_primary: boolean;
  is_bill_payer: boolean;
  can_view_notes: boolean;
  can_view_invoices: boolean;
  can_request_changes: boolean;
};

export type ClientDetail = {
  id: number;
  code: string;
  name: string;
  status: ClientStatus;
  ic_number: string | null;
  dob: string | null;
  age: number | null;
  gender: "female" | "male" | "other" | null;
  address: string | null;
  area: string | null;
  postcode: string | null;
  mobility_level: string | null;
  languages: string | null;
  allergies: string | null;
  notes: string | null;
  consent_given_at: string | null;
  consent_by: string | null;
  login: { id: number; email: string; status: string } | null;
  care_plans: {
    id: number;
    version: number;
    status: "draft" | "active" | "superseded";
    effective_from: string | null;
    agreed_by: string | null;
    tasks: number;
  }[];
  family: FamilyLink[];
  assignments: {
    id: number;
    caregiver: string | null;
    service: string | null;
    status: string;
    start_date: string | null;
    end_date: string | null;
  }[];
};

export type PlanTask = {
  id?: number;
  category: string;
  description: string;
  frequency: string;
  time_of_day: string;
};

export type CarePlanDetail = {
  id: number;
  patient_id: number;
  client: string | null;
  consent: boolean;
  version: number;
  status: "draft" | "active" | "superseded";
  effective_from: string | null;
  effective_to: string | null;
  agreed_by: string | null;
  agreed_at: string | null;
  author: string | null;
  notes: string | null;
  tasks: PlanTask[];
};

export type ClientOptions = {
  statuses: ClientStatus[];
  mobility: string[];
  categories: Record<string, string>;
  frequencies: Record<string, string>;
  times: Record<string, string>;
};

export type Credentials = { name: string; email: string; password: string; suspended: boolean };

/** "walks_with_aid" -> "Walks with aid" */
export function humanise(value: string | null | undefined): string {
  if (!value) return "";
  const s = value.replace(/_/g, " ");
  return s.charAt(0).toUpperCase() + s.slice(1);
}

// ---- Caregivers and placements (phase 4) ----

export type CaregiverStatus = "applicant" | "vetting" | "active" | "inactive" | "left";

export type CaregiverSummary = {
  id: number;
  code: string;
  name: string | null;
  area: string | null;
  status: CaregiverStatus;
  placeable: boolean;
  check_expiring: boolean;
};

export type CaregiverDetail = CaregiverSummary & {
  email: string | null;
  phone: string | null;
  user_id: number;
  login_status: string | null;
  last_login_at: string | null;
  ic_number: string | null;
  gender: "female" | "male" | "other" | null;
  dob: string | null;
  languages: string | null;
  skills: string | null;
  base_area: string | null;
  has_own_transport: boolean;
  max_travel_km: number | null;
  hourly_rate: number | null;
  police_check_expires_at: string | null;
  right_to_work_verified: boolean;
  assignments: {
    id: number;
    client: string | null;
    service: string | null;
    role: string;
    status: string;
    start_date: string | null;
    end_date: string | null;
  }[];
};

export type PlacementOptions = {
  caregivers: {
    id: number;
    name: string | null;
    code: string;
    area: string | null;
    gender: string | null;
    languages: string | null;
  }[];
  services: {
    id: number;
    name: string;
    category: string;
    unit: string;
    base_rate: number;
    needs_care_plan: boolean;
  }[];
  client_has_care_plan: boolean;
};

export type AssignmentDetail = {
  id: number;
  client: { id: number; name: string | null; code: string | null };
  caregiver: { id: number; name: string | null; code: string | null };
  service: { name: string; unit: string } | null;
  role: "primary" | "relief";
  status: "proposed" | "active" | "ended";
  one_off: boolean;
  start_date: string | null;
  end_date: string | null;
  charge_rate: number | null;
  notes: string | null;
  upcoming: OfficeShift[];
  recent: OfficeShift[];
};

// ---- Billing (phase 5) ----

export type InvoiceStatus = "draft" | "sent" | "part_paid" | "paid" | "overdue" | "void";

export type InvoiceSummary = {
  id: number;
  number: string;
  client: string | null;
  client_id: number;
  bill_to: string | null;
  period: string | null;
  status: InvoiceStatus;
  status_label: string;
  total: number;
  balance: number;
  due_date: string | null;
};

export type InvoiceDetail = InvoiceSummary & {
  bill_to_email: string | null;
  period_start: string | null;
  period_end: string | null;
  issued_at: string | null;
  subtotal: number;
  adjustments: number;
  amount_paid: number;
  can_void: boolean;
  can_pay: boolean;
  lines: { id: number; description: string; quantity: number; rate: number; amount: number; from_shift: boolean }[];
  payments: { id: number; amount: number; method: string; reference: string | null; paid_on: string | null; recorded_by: string | null }[];
  methods: { value: string; label: string }[];
};

export type BillingPrepare = {
  month: string;
  label: string;
  clients: { id: number; name: string | null; code: string; shifts: number; amount: number }[];
};

/** RM 1,234.50 */
export function rm(n: number): string {
  return `RM ${n.toLocaleString("en-MY", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

// ---- Families and clients (phase 6) ----

/** A family member or the client themselves: they see visits, not shifts to work. */
export function isFamily(role: string | undefined): boolean {
  return role === "guardian" || role === "patient";
}

export type FamilyVisit = {
  id: number;
  date: string;
  start: string;
  end: string;
  caregiver: string | null;
  service: string | null;
  status: Shift["status"];
  arrived: string | null;
  left: string | null;
  done: string[];
  notes: string | null;
  reason: string | null;
};

export type FamilyConcern = { id: number; date: string; category: string; open: boolean };

export type ConcernCategory = { value: string; label: string };

export type FamilyClient = {
  id: number;
  name: string;
  can_view_notes: boolean;
  can_view_invoices: boolean;
  can_request_changes: boolean;
  coming: FamilyVisit[];
  visits: FamilyVisit[];
  plan: { agreed_at: string | null; agreed_by: string | null; tasks: { description: string; frequency: string | null }[] } | null;
  invoices: {
    id: number;
    number: string;
    period: string | null;
    total: number;
    balance: number;
    due_date: string | null;
    status: InvoiceStatus;
    status_label: string;
  }[];
  concerns: FamilyConcern[];
  categories: ConcernCategory[];
  bank: { bank: string | null; account_name: string | null; account_number: string } | null;
};

export type MyCare = {
  name: string;
  coming: FamilyVisit[];
  concerns: FamilyConcern[];
  categories: ConcernCategory[];
};

// ---- Staff and audit (phase 7) ----

export type StaffAccount = {
  id: number;
  name: string;
  email: string;
  role: "admin" | "coordinator";
  status: "invited" | "active" | "suspended";
  last_login_at: string | null;
  two_factor: boolean;
  is_me: boolean;
};

export type AuditEntry = {
  id: number;
  at: string | null;
  user: string | null;
  action: string;
  subject: string | null;
  detail: string | null;
  ip: string | null;
};

export type AuditPage = {
  entries: AuditEntry[];
  more: boolean;
  users: { id: number; name: string }[];
  actions: string[];
};
