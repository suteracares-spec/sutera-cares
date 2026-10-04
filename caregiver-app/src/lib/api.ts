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
  user: { name: string; email: string; code: string | null; status: string };
  password_change_required: boolean;
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
  options: { method?: "GET" | "POST"; body?: unknown; token?: string | null } = {}
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
