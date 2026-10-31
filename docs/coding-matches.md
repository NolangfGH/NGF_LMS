# Two-student coding challenges

Open **Teacher Dashboard → Challenges** (`/teacher-challenges`). The original builder for a whole section remains available under **Whole-section challenges** (`/teacher-section-challenges`). Existing assignment and quiz timers are unchanged.

## Teacher workflow

1. Choose two different enrolled students. Each student picker has its own grade/section filter; both students may be in one section or in different sections you teach.
2. Enter a task, success criteria, exact sample output, shared time limit (1–180 minutes), award name, and winner XP (0–10,000). Send the invitations. The task and reward are fixed for that match; cancel and create a new match if they need to change.
3. Students receive persistent in-app notifications. You receive notifications when each responds, when both accept, and when a version is submitted. Notifications appear on authenticated dashboards and learning pages and refresh every eight seconds while the page is visible. They do not send email or browser push alerts.
4. After both accept, a five-second preparation countdown precedes the common start time. The monitor shows both saved drafts, revision numbers, save times, and submitted versions. Draft polling is every three seconds, not a WebSocket connection. An unsaved keystroke is not yet visible to the teacher.
5. Download submitted `.cpp` versions and test them in your classroom compiler. Give actionable feedback. A **correct** verdict requires the observed output and confirmation that you tested the submitted version against the task. Output comparison normalizes line endings and ignores trailing newline characters; other spaces are preserved. Matching typed output alone does not prove a program is correct: the teacher remains responsible for testing its code and behavior.
6. The system recommends the earliest **submitted version that you verify as correct**, ordered by the database submission time and then submission ID. Review all earlier pending versions first. Later review time never moves a student's submission later in the race. You can change verification before the final award.
7. Confirm the winner, write award feedback, and finalize. The configured XP is added to the winner's existing total and level once, including in the existing leaderboard. Both students see the result and their feedback. Final awards cannot be edited or cancelled.

The time limit is a target rather than a hard stop for these matches. After the deadline, students may still save and submit until the teacher finalizes or cancels the match. Those submissions are explicitly marked **Overtime**. A correct solution submitted in overtime can win. There is no automatic award at the deadline.

## Student workflow

Open **Challenges → Your coding matches** or a challenge notification. Read the task and accept or decline. Declining closes the match without a winner. After accepting, wait for the other coder and the shared preparation countdown.

The private editor autosaves after 750 milliseconds without typing. **Submit for teacher verification** first saves the draft and then records an immutable copy. Editing later creates a new revision. Repeated submission of the same revision does not create duplicate copies. Only submitted versions compete for the award; a draft on the teacher's screen is not a submitted solution. Feedback indicates what to improve. Students can save and submit improvements until the match ends.

The editor does not execute C++ programs on the hosting server. Use **Download my code** and the classroom compiler to test. Students cannot see their opponent's source, revisions, or feedback. Public names respect the existing leaderboard nickname privacy preference.

## Reliability and hosting

- All writes use server-side session ownership checks, CSRF, validation, database transactions and parent match locks. Browser clocks never authorize a save or decide whether a submission was on time.
- Revision checks prevent another tab from silently overwriting a newer saved draft. A conflict pauses editing and asks the student to download their local code before reloading. Failed network saves leave the code in the editor and show an unsaved warning. Navigation with unsaved edits triggers a browser warning.
- A student leaving the teacher's roster blocks acceptance, coding, and awards. The teacher can close that match and create one for the current roster. Existing work stays in the history.
- Notifications remain unread until marked read. Marking notices read does not dismiss unanswered invitations. Quiet notification preferences do not hide invitations needing a response.
- Ended matches keep code, submitted snapshots, verdicts, timestamps, feedback, and the final award. No student source is executed server-side. Code downloads use attachment headers.
- Polls pause when a page is hidden. Stale/network error messages preserve the last successful data and unsaved teacher feedback. Polling limits are keyed by session and IP to support multiple students behind a school's shared network.
- Uses ordinary Laravel requests, local JavaScript, and the existing Tailwind CDN. No npm build, WebSocket server, queue worker, paid AI service, or extra hosting process is required.

Deploy the code and run `php artisan migrate --force`. Keep the database and storage backed up as for the existing application. New records use four tables: `coding_matches`, `coding_match_participants`, `coding_match_submissions`, and `challenge_notices`.

Validation: `php artisan test --filter=CodingMatchesTest` and `node --test tests/Frontend/coding-matches.test.cjs`.
