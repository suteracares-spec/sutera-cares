import AsyncStorage from "@react-native-async-storage/async-storage";
import NetInfo from "@react-native-community/netinfo";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from "react";
import { AppState } from "react-native";

import {
  ApiError,
  OfflineError,
  request,
  type Shift,
  type ShiftDetail,
  type ShiftLists,
} from "./api";
import { useAuth } from "./auth";
import { DETAILS_KEY, LISTS_KEY, QUEUE_KEY } from "./keys";

/*
 * Shifts, cached on the phone, and the outbox of check-ins and check-outs.
 *
 * A caregiver in a lift or a basement taps "check in" and it must work.
 * So every action goes into a queue first, stamped with the time of the
 * tap, and the screen shows it as done straight away. The queue is sent
 * in order whenever there is signal: on tap, when the network returns,
 * when the app comes back to the front, and every minute while anything
 * is waiting. The server accepts the tapped time and treats a repeat as
 * already done, so sending twice is harmless.
 */

export type QueuedAction = {
  id: string;
  shiftId: number;
  kind: "check-in" | "check-out";
  body: Record<string, unknown> & { at: string };
  queuedAt: string;
};

/** An action the office refused (e.g. too early, already cancelled). Shown until dismissed. */
export type Problem = {
  id: string;
  shiftId: number;
  kind: QueuedAction["kind"];
  message: string;
  client?: string;
};

type Visits = {
  lists: ShiftLists | null;
  detail: (id: number) => ShiftDetail | undefined;
  queue: QueuedAction[];
  problems: Problem[];
  online: boolean;
  syncing: boolean;
  lastSynced: string | null;
  refresh: () => Promise<void>;
  loadDetail: (id: number) => Promise<void>;
  checkIn: (id: number, lat?: number, lng?: number) => Promise<void>;
  checkOut: (id: number, body: CheckOutBody) => Promise<void>;
  dismissProblem: (id: string) => void;
  pendingFor: (id: number) => QueuedAction[];
};

export type CheckOutBody = {
  tasks: number[];
  notes: string;
  concern_flagged: boolean;
  concern_category?: string;
  concern_detail?: string;
};

const VisitsContext = createContext<Visits | null>(null);

/**
 * One store per signed-in session: a new sign-in starts from what is on
 * the phone for that account, never from the previous person's screen.
 */
export function SessionVisitsProvider({ children }: { children: ReactNode }) {
  const { token } = useAuth();
  return <VisitsProvider key={token ?? "signed-out"}>{children}</VisitsProvider>;
}

export function VisitsProvider({ children }: { children: ReactNode }) {
  const { token: authToken, profile, expired } = useAuth();
  // Office accounts have no shifts and no visits to queue.
  const token = profile?.user.office ? null : authToken;
  const [lists, setLists] = useState<ShiftLists | null>(null);
  const [details, setDetails] = useState<Record<number, ShiftDetail>>({});
  const [queue, setQueue] = useState<QueuedAction[]>([]);
  const [problems, setProblems] = useState<Problem[]>([]);
  const [online, setOnline] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [lastSynced, setLastSynced] = useState<string | null>(null);

  // The queue is read inside async loops; a ref always has the latest.
  const queueRef = useRef<QueuedAction[]>([]);
  const flushing = useRef(false);

  const saveQueue = useCallback(async (next: QueuedAction[]) => {
    queueRef.current = next;
    setQueue(next);
    await AsyncStorage.setItem(QUEUE_KEY, JSON.stringify(next));
  }, []);

  const saveDetail = useCallback((d: ShiftDetail) => {
    setDetails((prev) => {
      const next = { ...prev, [d.id]: d };
      AsyncStorage.setItem(DETAILS_KEY, JSON.stringify(next)).catch(() => {});
      return next;
    });
  }, []);

  // Restore what the phone already knows, before any network.
  useEffect(() => {
    (async () => {
      const [l, d, q] = await AsyncStorage.multiGet([LISTS_KEY, DETAILS_KEY, QUEUE_KEY]);
      if (l[1]) setLists(JSON.parse(l[1]));
      if (d[1]) setDetails(JSON.parse(d[1]));
      if (q[1]) {
        queueRef.current = JSON.parse(q[1]);
        setQueue(queueRef.current);
      }
    })();
  }, []);

  const handleError = useCallback(
    async (e: unknown): Promise<"offline" | "refused"> => {
      if (e instanceof OfflineError) return "offline";
      if (e instanceof ApiError && e.status === 401) {
        await expired();
      }
      return "refused";
    },
    [expired]
  );

  /** Send the queue, oldest first. Stops at the first sign of no signal. */
  const flush = useCallback(async () => {
    if (!token || flushing.current) return;
    flushing.current = true;
    setSyncing(true);
    try {
      while (queueRef.current.length > 0) {
        const item = queueRef.current[0];
        try {
          const d = await request<ShiftDetail>(`/shifts/${item.shiftId}/${item.kind}`, {
            method: "POST",
            token,
            body: item.body,
          });
          saveDetail(d);
        } catch (e) {
          const outcome = await handleError(e);
          if (outcome === "offline") break;
          if (e instanceof ApiError && e.status === 401) break;
          // Refused for a reason that will not change by retrying: tell the
          // caregiver, drop it, and fetch the shift as the office has it.
          setProblems((p) => [
            ...p,
            {
              id: item.id,
              shiftId: item.shiftId,
              kind: item.kind,
              message: e instanceof ApiError ? e.firstMessage : "The office could not accept this.",
            },
          ]);
          request<ShiftDetail>(`/shifts/${item.shiftId}`, { token })
            .then(saveDetail)
            .catch(() => {});
        }
        await saveQueue(queueRef.current.slice(1));
      }
    } finally {
      flushing.current = false;
      setSyncing(false);
    }
  }, [token, saveDetail, saveQueue, handleError]);

  const refresh = useCallback(async () => {
    if (!token) return;
    await flush();
    try {
      const l = await request<ShiftLists>("/shifts", { token });
      setLists(l);
      setLastSynced(new Date().toISOString());
      setOnline(true);
      await AsyncStorage.setItem(LISTS_KEY, JSON.stringify(l));
    } catch (e) {
      if ((await handleError(e)) === "offline") setOnline(false);
    }
  }, [token, flush, handleError]);

  const loadDetail = useCallback(
    async (id: number) => {
      if (!token) return;
      try {
        saveDetail(await request<ShiftDetail>(`/shifts/${id}`, { token }));
      } catch (e) {
        if ((await handleError(e)) === "offline") setOnline(false);
      }
    },
    [token, saveDetail, handleError]
  );

  const enqueue = useCallback(
    async (shiftId: number, kind: QueuedAction["kind"], body: Record<string, unknown>) => {
      const now = new Date().toISOString();
      await saveQueue([
        ...queueRef.current,
        {
          id: `${kind}-${shiftId}-${Date.now()}`,
          shiftId,
          kind,
          body: { ...body, at: now },
          queuedAt: now,
        },
      ]);
      flush();
    },
    [saveQueue, flush]
  );

  // Send whenever signal comes back, the app returns to the front, or a
  // minute passes with something waiting.
  useEffect(() => {
    const unsubscribeNet = NetInfo.addEventListener((s) => {
      const isOnline = !!s.isConnected && s.isInternetReachable !== false;
      setOnline(isOnline);
      if (isOnline) flush();
    });
    const appState = AppState.addEventListener("change", (s) => s === "active" && refresh());
    const timer = setInterval(() => queueRef.current.length && flush(), 60_000);
    return () => {
      unsubscribeNet();
      appState.remove();
      clearInterval(timer);
    };
  }, [flush, refresh]);

  const value = useMemo<Visits>(() => {
    const pendingFor = (id: number) => queue.filter((q) => q.shiftId === id);

    return {
      lists: lists ? applyPendingToLists(lists, queue, details) : null,
      detail: (id) => {
        const d = details[id] ?? findInLists(lists, id);
        return d ? applyPending(d as ShiftDetail, pendingFor(id)) : undefined;
      },
      queue,
      problems,
      online,
      syncing,
      lastSynced,
      refresh,
      loadDetail,
      checkIn: (id, lat, lng) => enqueue(id, "check-in", { lat, lng }),
      checkOut: (id, body) => enqueue(id, "check-out", body),
      dismissProblem: (id) => setProblems((p) => p.filter((x) => x.id !== id)),
      pendingFor,
    };
  }, [lists, details, queue, problems, online, syncing, lastSynced, refresh, loadDetail, enqueue]);

  return <VisitsContext.Provider value={value}>{children}</VisitsContext.Provider>;
}

export function useVisits(): Visits {
  const v = useContext(VisitsContext);
  if (!v) throw new Error("useVisits outside VisitsProvider");
  return v;
}

/** What the screen should show: the office's version, plus what is still on its way. */
function applyPending(d: ShiftDetail, pending: QueuedAction[]): ShiftDetail {
  let out = d;
  for (const p of pending) {
    if (p.kind === "check-in" && out.status === "scheduled") {
      out = {
        ...out,
        status: "in_progress",
        check_in_problem: null,
        visit: {
          check_in_at: p.body.at,
          check_out_at: null,
          tasks_completed: [],
          notes: null,
          concern_flagged: false,
        },
      };
    }
    if (p.kind === "check-out" && out.status === "in_progress") {
      const ids = (p.body.tasks as number[]) ?? [];
      out = {
        ...out,
        status: "completed",
        visit: {
          check_in_at: out.visit?.check_in_at ?? null,
          check_out_at: p.body.at,
          tasks_completed:
            out.plan?.tasks.filter((t) => ids.includes(t.id)).map((t) => t.description) ?? [],
          notes: (p.body.notes as string) ?? null,
          concern_flagged: !!p.body.concern_flagged,
        },
      };
    }
  }
  return out;
}

// A visit only moves forward: booked, checked in, then done (or missed,
// or cancelled). When the list and a shift's own record disagree, the one
// further along is the newer.
const PROGRESS: Record<Shift["status"], number> = {
  scheduled: 0,
  in_progress: 1,
  completed: 2,
  missed: 2,
  cancelled: 2,
};

function applyPendingToLists(
  lists: ShiftLists,
  queue: QueuedAction[],
  details: Record<number, ShiftDetail>
): ShiftLists {
  const status = (s: Shift): Shift["status"] => {
    const kinds = queue.filter((q) => q.shiftId === s.id).map((q) => q.kind);
    if (kinds.includes("check-out")) return "completed";
    const known = details[s.id]?.status;
    const latest = known && PROGRESS[known] > PROGRESS[s.status] ? known : s.status;
    if (kinds.includes("check-in") && latest === "scheduled") return "in_progress";
    return latest;
  };
  const map = (l: Shift[]) => l.map((s) => ({ ...s, status: status(s) }));
  return {
    ...lists,
    today: map(lists.today),
    unfinished: map(lists.unfinished),
    coming: map(lists.coming),
  };
}

function findInLists(lists: ShiftLists | null, id: number): Shift | undefined {
  return lists
    ? [...lists.unfinished, ...lists.today, ...lists.coming].find((s) => s.id === id)
    : undefined;
}
