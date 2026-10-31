# Teacher Settings

Open /teacher-settings after signing in as a teacher. The Settings menu in the
teacher dashboard, assignment builder, lesson pages and reports opens this page.

The supplied design is adapted to working local account and classroom features.
It uses Tailwind CDN and ordinary Laravel assets without a Node build.

## Account and classroom profile

Account details save first name, surname, email and school. Changing email
requires the current password and ends other teacher sessions. The new email
is used for future sign-in.

Display name and a 300-character introduction appear on enrolled students'
Learn page. Turning profile visibility off uses the account name and hides
the bio and photo from students. Text is escaped when rendered.

Photos allow JPG, PNG and WebP up to 2 MB and 4000 by 4000 pixels. Files are
stored privately. Only the owner or an enrolled student with profile visibility
enabled can view the photo. SVG uploads are not accepted.

## Classroom management

Create and edit grade/section classrooms, subject names and periods. Each
section has a unique generated join code. Editing grade/section labels updates
enrollment labels without changing student XP or activity timestamps.

Pause new enrollment to stop code verification and account registration.
Existing students can still sign in and continue their work. Reopening
enrollment enables registration again. Existing accounts and work are retained.

Replacing a join code requires the current password and an explicit checkbox.
The previous code and previously verified registration sessions stop working.
Registered student accounts remain valid.

## Activity and accessibility

Activity preferences control the summary cards shown on Settings:
new enrollments this week, latest code submissions awaiting review, and
learners with a lesson help/confidence signal. These are in-app summaries;
no email service or fake compiler diagnostics are presented.

Text size, high contrast and reduced motion preview immediately and are
saved to the teacher account. They are applied on the teacher dashboard,
assignment builder, quiz builder/reviews, lesson pages, reports, Code Lab
reviews and achievement pages. Browser/device accessibility features can
also be used independently.

Each section has its own save action. The unsaved bar saves the most recently
edited section; discarding resets all unsaved forms. Navigating away while
other sections have unsaved changes triggers the browser's native warning.
Password and photo inputs are never saved to browser storage.

## Passwords and sessions

New passwords require at least ten characters containing letters and numbers,
confirmation, and the current password. Passwords use Laravel's hashed cast.
Password changes and Sign out other teacher sessions increase the account's
session version. Other teacher sessions lose authorization on their next web
request. The current session is regenerated and remains signed in.

Existing sessions from before this feature are accepted at version one.
After a version change, a session without the current version must sign in.
Student sessions are not invalidated when a teacher session is revoked.

## Deployment and checks

    php artisan migrate --force
    php artisan view:cache
    php artisan test --filter=TeacherSettingsTest
    node --test tests/Frontend/teacher-settings.test.cjs

The Node command is an optional developer check using only built-in modules.
It is not required to deploy or run the application.
