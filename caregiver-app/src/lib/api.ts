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
