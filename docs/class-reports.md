# Class reports

Open /reports after signing in as a teacher. The dashboard Reports menu and
View Class Report button now open this page.

The page adapts the supplied green educator report design. Data comes from
the current teacher's sections, current enrolled students, lesson progress,
saved quiz submissions and final Code Lab reviews. It does not use simulated
compiler activity, fabricated diagnoses or external AI calls.

## Controls

- Select one section or all owned sections.
- Use all saved history, this week, this month, or custom inclusive calendar
  dates in Asia/Singapore (UTC+08:00). Custom ranges allow up to 366 days.
- Search the roster, filter coaching status, sort by name, XP, lesson count,
  quiz average or last learning activity, and inspect student evidence.
- Export CSV uses the same roster filters and includes all matching students.
  Formula-like text is escaped for spreadsheet safety.
- Print / Save PDF opens a full filtered roster, including students beyond the
  normal 25-row pagination. The browser supplies printing or saving as PDF.
- Apply / refresh reloads current saved records. Reading reports does not
  award XP, finalize attempts, alter grades or modify student records.

## Metric definitions

Lesson completion counts checkpoints first completed within the selected dates
divided by seven lessons per current enrolled student. Self-study is included.
Checkpoint completion is formative evidence, not programming mastery.

Quiz averages use the best fully graded percentage per student and quiz in
the selected period. Each quiz has equal weight. Partial grading is excluded;
final opportunities do not count as extra quizzes.

Coding submissions count different workspaces submitted during the selected
period. Revisions count once in that period. A strong reviewed solution needs
the latest valid final teacher review per workspace to score at least 3/4 for
both correctness and logic. Review totals and rubric values must be consistent.
AI suggestions without a final teacher review are excluded.

The activity chart shows per-interval counts, not cumulative totals. With all
saved history selected, it displays the latest 42 calendar days. Last activity
uses saved lesson visits, checkpoint completions, quiz submissions and code
submissions. Account edits are not attendance evidence.

Coaching signals use lesson help requests or a needs-help confidence choice,
a quiz average below 60%, or a final review below 2/4 for correctness or logic.
These are report rules for a teacher to investigate, not a diagnosis or a
claim of an official curriculum benchmark. Current confidence and help requests
are shown for lessons visited or completed in the period.

Assignment and quiz performance uses currently published activities targeting
the selected roster, including multiple-section targets. Archived activities
may retain history in aggregate totals. Reports use current enrollment and
current XP; they do not reconstruct past enrollment or historical XP balances.
The roster search and coaching filter affect the roster, print view and CSV;
the top class summaries retain the full section/date selection.

## Verification

    php artisan test --filter=ClassReportsTest
    node --test tests/Frontend/reports.test.cjs

The application uses the existing Tailwind CDN setup and needs no Node build,
npm dependency, database migration or additional AI key for this page.
