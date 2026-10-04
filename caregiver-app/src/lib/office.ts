import { useCallback, useEffect, useState } from "react";

import { ApiError, OfflineError, request } from "./api";
import { useAuth } from "./auth";

type Result<T> = { key: string; data: T | null; error: string | null };

/**
 * Loads one office API resource. The office view is read live, not
 * cached: it is for seeing the current picture, and a coordinator on a
 * phone with no signal should see "no connection", not a stale day.
 *
 * "Loading" is derived (no result yet for this request) rather than set,
 * and a response for a request that has since changed, e.g. the previous
 * day after tapping "Next", is ignored.
 */
export function useOffice<T>(path: string | null) {
  const { token, expired } = useAuth();
  const [attempt, setAttempt] = useState(0);
  const [result, setResult] = useState<Result<T> | null>(null);
  const key = `${path}#${attempt}`;

  useEffect(() => {
    if (!token || !path) return;
    let current = true;

    request<T>(path, { token })
      .then((data) => current && setResult({ key, data, error: null }))
      .catch(async (e) => {
        if (e instanceof ApiError && e.status === 401) {
          await expired();
          return;
        }
        if (!current) return;
        const message =
          e instanceof OfflineError || e instanceof ApiError ? e.message : "Something went wrong.";
        // Keep what is already on screen for this path; just say it is out of date.
        setResult((prev) => ({
          key,
          data: prev && prev.key.startsWith(`${path}#`) ? prev.data : null,
          error: message,
        }));
      });

    return () => {
      current = false;
    };
  }, [token, path, key, expired]);

  const reload = useCallback(() => setAttempt((n) => n + 1), []);
  const samePath = result?.key.startsWith(`${path}#`) ?? false;

  return {
    data: samePath ? result!.data : null,
    error: result?.key === key ? result.error : null,
    loading: result?.key !== key,
    reload,
  };
}
