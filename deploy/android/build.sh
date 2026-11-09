#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SDK="${ANDROID_HOME:?Set ANDROID_HOME to an installed Android SDK}"
TOOLS="$SDK/build-tools/36.0.0"
PLATFORM="$SDK/platforms/android-36/android.jar"
OUT="$ROOT/storage/app/android-build"
mkdir -p "$OUT/classes" "$OUT/generated" "$OUT/dex"
"$TOOLS/aapt2" compile --dir "$ROOT/deploy/android/res" -o "$OUT/resources.zip"
"$TOOLS/aapt2" link -I "$PLATFORM" --manifest "$ROOT/deploy/android/AndroidManifest.xml" --java "$OUT/generated" --min-sdk-version 26 --target-sdk-version 36 -o "$OUT/base.apk" "$OUT/resources.zip"
mapfile -t SOURCES < <(find "$ROOT/deploy/android/src" "$OUT/generated" -name '*.java')
javac --release 8 -classpath "$PLATFORM" -d "$OUT/classes" "${SOURCES[@]}"
mapfile -t CLASSES < <(find "$OUT/classes" -name '*.class')
"$TOOLS/d8" --release --min-api 26 --lib "$PLATFORM" --output "$OUT/dex" "${CLASSES[@]}"
(cd "$OUT/dex" && zip -q -j "$OUT/base.apk" classes*.dex)
"$TOOLS/zipalign" -f -p 4 "$OUT/base.apk" "$OUT/NGF-CodeQuest-1.0.0-unsigned.apk"
cp "$TOOLS/lib/apksigner.jar" "$OUT/apksigner.jar"
sha256sum "$OUT/NGF-CodeQuest-1.0.0-unsigned.apk"
