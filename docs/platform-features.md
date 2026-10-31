# NGF_C++CodeQuest classroom platform

This release adds the missing LMS workflows to the existing Laravel application. It keeps existing accounts, assignments, quiz results, private blog conversations and XP balances. No sample students or invented learning results were added.

## Where to start

| Workflow | Page |
| --- | --- |
| Student sign-in and dashboard | `/student-login`, `/student-dashboard` |
| Student curriculum and quest map | `/learn` |
| Student coding workspace | `/code-lab` |
| Assignments, quizzes and challenges | `/student-assignments`, `/quiz`, `/challenges` |
| Achievements, rankings and progress | `/achievements`, `/leaderboard`, `/progress` |
| Teacher overview | `/teacher-dashboard` |
| Student management | `/teacher-students` |
| Assignment/test-case builder | `/assignments` |
| Quiz builder | `/teacher-quizzes` |
| Lesson assignments and editable content | `/teacher-lessons`, `/teacher-content` |
| Code review, grades and reports | `/teacher-code-lab`, `/grading-sheet`, `/reports` |
| Class settings and archiving | `/teacher-settings#classes` |
| Private discussions and voice responses | `/teacher-blog`, `/blog` |
| School administration | `/school-admin` (school administrator only) |

Student and teacher dashboards display saved learning data and refresh their summaries every 30 seconds. Classroom notifications poll every eight seconds. Gradebooks refresh every 15 seconds while the teacher is not editing. Polling is used for shared-hosting compatibility; this is not a WebSocket deployment.

## Student learning

The default curriculum now contains 13 lessons covering introduction, output, variables, input, arithmetic operators, conditions, for loops, sentinel while loops, functions, arrays, strings, debugging and a supplies mini-project. Lessons include explanations, worked C++ examples/output, three knowledge questions, reflection, bookmarks, previous/next navigation and a linked coding practice.

The quest map shows actual completed/unlocked/locked missions. Class settings can require preceding checkpoints before later lessons unlock; the server enforces those locks. Open learning remains the default for existing classes so a deployment does not unexpectedly block their current work. Publishing or unpublishing a teacher's content affects only that teacher's sections. Historical checkpoint evidence is retained when the content changes.

Each first checkpoint completion earns 25 XP, once per lesson. Each first fully passing configured practice test suite earns 30 XP, once per activity. Existing completed checkpoints are not silently paid again. Existing registration, quiz, assignment opening, challenge and private-blog assessment reward rules remain intact. The unique reward records and student locks prevent duplicate payments on refresh/retry. Level remains one plus total XP divided into 600-point steps.

Achievements now include First Program, Bug Hunter, Loop Master, Quiz Champion, Challenge Solver and a seven-day coding streak, alongside the original milestones. They require recorded test, quiz, submission or teacher-review evidence. A raw XP total cannot award a skill badge. Consecutive learning days are calculated in Asia/Singapore; the older Learning Rhythm badge still recognizes three distinct learning days. Celebration messages and confetti respect reduced-motion preferences.

Leaderboards offer all-time, current-week and current-month views across the teacher's grade sections or one section. Period views use dated XP events; the deployment records existing balances separately and does not invent dates for historical points. Nickname privacy settings remain respected. The weekly class goal is editable in class settings; it is a shared progress target, not a public failure label.

## Code Lab and automatic feedback

The editor retains draft saving, revision conflicts, starter reset, downloads, explanations, timed assignments and immutable submissions. It now adds syntax highlighting, an accessible Escape-then-Tab way out of indentation mode, custom stdin, Run C++, weighted tests, output/diagnostic consoles, run history, progressive hints, beginner explanations and test evidence linked to the submitted revision.

Teachers can supply optional starter C++, an assignment sample output, up to eight visible/hidden tests with individual weights, and five hints. Hidden inputs, expected outputs and diagnostics stay private to the teacher. Partial test scores are reported separately from the final teacher rubric. Only the teacher finalizes correctness, logic, readability and explanation scores, and can request a revised submission. A revision request does not bypass existing deadlines or approved medical/excuse opportunity rules.

**Execution is prepared but requires a separate running Judge0 host.** No compiler was installed on this Windows computer and no remote host was provisioned. The app shows this honestly and does not execute student programs in Laravel or substitute fabricated output. Follow [the self-hosted compiler guide](self-hosted-compiler.md), set the URL/token/language ID locally, and run `php artisan compiler:check`. API integration tests use simulated Judge0 responses; they are not an attestation of a deployed sandbox. Before classroom use, verify real execution, time/memory enforcement and network isolation on the chosen host.

## Assessment and teacher monitoring

Quizzes retain multiple choice, identification, true/false and multiple answers. They also support code-output prediction, fill-in-code, debugging answers and logic-step ordering. Ordering works with drag gestures or Up/Down buttons for touch/keyboard access. Identification ignores capitalization; C++ and output answers preserve case while normalizing whitespace. Teachers choose timed questions (30–600 seconds each) or untimed assessment. Schedules still apply, and untimed attempts earn no speed bonus. A started attempt snapshots questions/order/timing; editing the quiz never rewrites it. The existing one-attempt and approved final-opportunity rules remain.

Grading sheets have separate section/year/Term 1–3 records, configurable weights and thresholds, manual scores/excused states, linked quiz and final Code Lab results, notes, atomic CSV import, safe CSV export and printing. Programming Challenges is now a separate category, starting at 0% to preserve existing weights; teachers can assign it weight while keeping the total at 100%. Raw weighted percentages remain clearly distinguished from official school-form/transmuted grades and from gamification XP.

Reports retain scoped progress, checkpoint evidence, quiz performance, topic completion, class trends and coaching cues. They now show real executed-test pass rates, compiler/runtime/output failure counts and overdue coding tasks without submissions. Dashboards show review queues, class join codes, enrollment and upcoming work. The existing individual student profiles provide histories, achievements, goals and teacher intervention information. Blog replies remain private between each student and teacher; text, image and voice responses are supported under the existing per-student assessment limits.

## Roles, classes and accessibility

Public teacher registration creates a teacher, never a school administrator. Bootstrap a trusted existing account from the project terminal:

```sh
php artisan school:promote-admin teacher@example.com
```

Replace that email with the intended registered teacher, then sign in again. School Administration can create teaching accounts, manage staff roles with password confirmation, and check the compiler. This is an administrator for this single application installation; it is not an automatically provisioned multi-school tenant service. Student records and private learning conversations remain scoped to the owning teacher. Existing decorative “System Admin” branding does not itself grant the administrative role.

Teachers can archive/restore their classes in Settings. Archiving pauses student login and enrollment, hides the class from active teaching scopes and retains its records. Restoring keeps enrollment closed until reviewed. Registration codes still match Grade 8 AM/BP/DB according to the project's existing enrollment policy.

Existing student/teacher settings provide text-size, contrast and reduced-motion preferences. New forms use labels, focus states, status messages and keyboard controls. Layouts are responsive, the sidebars scroll, and the app uses small polling requests instead of persistent sockets. PHP serves the application without npm/Vite or a Node production runtime. The external compiler is a separate deployment.

## Install / verify

```sh
composer install
php artisan migrate --force
php artisan config:clear
php artisan view:cache
php artisan test --compact
php artisan serve --host=127.0.0.1 --port=8000
```

Development-only browser-logic checks:

```sh
node --test tests/Frontend/*.test.cjs
```

For shared hosting, point the domain's document root at `public/`, keep `.env` and private storage outside that root, use production database credentials/HTTPS and `APP_DEBUG=false`, and configure the documented scheduler for timer finalization when no browser is open. The app preserves existing data while migrations add run records, XP history, editable lesson content, class state, roles and quiz timing snapshots.
