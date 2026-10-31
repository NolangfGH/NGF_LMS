# Student Settings

Open /student-settings after signing in as a student. Settings is linked from
the student dashboard and shared desktop/mobile student navigation.

## Profile and companions

Students can update their classroom callsign using 3–15 ASCII letters, numbers
and hyphens with at least one letter or number. Student ID, real name, grade,
section, XP and level are not editable from this page. Preference and cosmetic
edits do not update student activity timestamps or award XP.

Companion choices use config/student_settings.php. The eight companions from
the supplied design are included, alongside the original Byte Owl and Solar
registration choices. Available companions can be changed without spending XP.
Level companions require saved XP thresholds. Code Wizard requires the earned
C++ Problem Solver badge. Unlocks are checked server-side; registration cannot
select a locked companion.

## In-app summaries and reading comfort

Choose summary cards for published activities closing in the next 24 hours,
saved teacher feedback notes and earned badges. These cards appear on Settings
and the Dashboard and refresh on page visits. They do not represent unread
messages or scheduled email/push notifications.

Quiet mode hides optional summaries. Quiz timers, assignment schedules and
teacher access continue normally.

Text size, contrast and reduced motion preview immediately. Saved preferences
apply across authenticated student pages, including the Code Lab editor.
The display and notification forms merge their preferences without overwriting
each other. Other devices see changes after refreshing.

Each section saves separately. The unsaved bar submits the last edited form.
Leaving with other unsaved sections triggers the browser's native warning.
Passwords are not saved to browser storage, and closing the password dialog
clears unsaved password inputs.

## Leaderboard privacy

Turning nickname visibility off replaces the nickname and chosen avatar with
a stable generic alias and avatar in classmates' overall and section rankings.
The student still sees their own nickname. Teacher reports retain the real
saved nickname for educational support. Rank, XP, level and section remain
visible. Existing open leaderboards update on their next refresh.

This is a classroom display preference, not a claim of anonymous data or a
legal compliance certification. Other students cannot access private settings,
quiz answers or submitted code through the authenticated application routes.

## Passwords and sign-out

Changing a password requires the current password and an eight-character
minimum new password containing letters and numbers. The new value is hashed.
Changing a password increments the student session version, regenerates the
current session and requires other student sessions to sign in again.
Registered accounts, saved work, quiz attempts and rewards remain intact.

Sign-out uses a CSRF-protected POST and clears student identity and class
verification from the current session. Teacher sessions are managed separately.
Version-one sessions from before this feature continue until a password change
invalidates their version.

## Deployment and checks

    php artisan migrate --force
    php artisan view:cache
    php artisan test --filter=StudentSettingsTest
    node --test tests/Frontend/student-settings.test.cjs

The Node command is an optional developer check. Deployment uses the existing
Tailwind CDN setup and does not require npm, a build step or an AI key.
