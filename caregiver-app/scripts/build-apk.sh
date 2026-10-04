#!/bin/bash
# Builds the signed release APK of the caregiver app, checks it, copies it
# to the project folder, and (with --install) installs it on the phone
# connected over USB.
#
#   bash scripts/build-apk.sh            # build only
#   bash scripts/build-apk.sh --install  # build and install on the USB phone
#
# Run in Git Bash from caregiver-app/. Takes about 15 minutes.
set -euo pipefail
cd "$(dirname "$0")/.."

VERSION=$(node -p "require('./app.json').expo.version")
OUT="../sutera-care-caregiver-${VERSION}.apk"
APK=android/app/build/outputs/apk/release/app-release.apk

# The release build must talk to the live portal.
unset EXPO_PUBLIC_API_URL

# A Gradle daemon (ours, or one Android Studio starts for its sync) keeps
# build files open, and Expo then cannot regenerate the android/ folder:
# EBUSY. Stop them first. Android Studio itself is left alone, but if it
# has this project open, close it for the build.
echo "build: stopping Gradle daemons"
powershell.exe -NoProfile -Command "Get-CimInstance Win32_Process -Filter \"Name='java.exe'\" | Where-Object { \$_.CommandLine -match 'GradleDaemon|KotlinCompileDaemon|kotlin-daemon' } | ForEach-Object { Stop-Process -Id \$_.ProcessId -Force }" || true
sleep 2

echo "build: syncing android/ with app.json (version $VERSION)"
CI=1 npx expo prebuild --platform android >/dev/null

echo "build: compiling (this is the slow part)"
(cd android && NODE_ENV=production ./gradlew assembleRelease --console=plain -q)

# Refuse an APK pointed at a test server.
if unzip -p "$APK" assets/index.android.bundle | grep -a -q "10.0.2.2"; then
    echo "build: STOPPED, this APK points at a local test server, not the live portal." >&2
    exit 1
fi

cp "$APK" "$OUT"
echo "build: done, $OUT ($(du -h "$OUT" | cut -f1))"

if [ "${1:-}" = "--install" ]; then
    echo "build: installing on the USB phone"
    adb -d install -r "$(cygpath -w "$OUT" 2>/dev/null || echo "$OUT")"
fi
