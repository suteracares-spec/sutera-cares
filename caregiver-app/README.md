# Sutera Care — caregiver app (Android)

The caregiver's phone view of the Sutera Care Provider portal, as an app:
today's shifts and the week ahead, check in (with location where the phone
allows it), tick the care plan's tasks, write the visit note, flag a concern,
check out.

Built with **Expo** (SDK 57) and **HeroUI Native** (styled through Uniwind /
Tailwind). It talks to the portal's API at
`https://providers.suteracares.org/portal/api/v1`, documented in
[`portal/routes/api.php`](../portal/routes/api.php).

## How it behaves without signal

Caregivers work in lifts, basements and stairwells. Every check-in and
check-out goes into a queue on the phone first, stamped with the time of the
tap, and the screen shows it as done at once. The queue is sent in order
whenever there is signal: straight away, when the network returns, when the
app is reopened, and every minute while anything is waiting. The portal
records the tapped time, keeps its own receipt time, and treats a repeat as
already done, so a retry can never create a second visit.

If the office refuses something (too early, the shift was cancelled, a
queued action more than a day old), the app says so in red and shows the shift
as the office has it.

Shifts, once opened, are kept on the phone so a visit can be done offline.
Signing out deletes them; actions not yet sent are kept and still go.

The sign-in token is kept in Android's encrypted keystore (SecureStore).

## Where things are

| | |
|---|---|
| `src/app/` | screens (Expo Router): `login`, `password` (first sign-in), `index` (my shifts), `shift/[id]` (the visit) |
| `src/lib/api.ts` | the API client and its types |
| `src/lib/auth.tsx` | sign-in state and the token |
| `src/lib/visits.tsx` | cached shifts and the offline queue |
| `src/components/` | shift card, sync banner |

## Running it

```
npm install
npx expo run:android
```

Uses the live portal unless `EXPO_PUBLIC_API_URL` is set. To work against a
local portal (`php artisan serve --host=0.0.0.0 --port=8126` in `portal/`), the
emulator reaches your computer at `10.0.2.2`:

```
EXPO_PUBLIC_API_URL=http://10.0.2.2:8126/api/v1 npx expo run:android
```

If `npm install` fails with `EALLOWSCRIPTS`, a global `allow-scripts` setting
in `~/.npmrc` is the cause; run it with `--userconfig` pointing at an empty
file.

## Release APK

See "Building the APK" below once the signing key exists. The key
(`*.keystore`) is never committed: lose it and installed copies can no longer
be updated in place.
