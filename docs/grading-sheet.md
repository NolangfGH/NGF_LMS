# Teacher grading sheets

Open `/grading-sheet` while signed in as a teacher, or choose **Grading Sheet** in the teacher menu. The page follows the supplied green gradebook design with the application's NGF branding, fixed header, scrolling sidebar, responsive table and learner evidence panel.

1. Choose a teaching section, consecutive school year (for example `2026-2027`) and Term 1, 2 or 3. Create the sheet once for that combination.
2. Confirm the grading weights and proficiency threshold with your school's policy. Starting weights from the supplied design are WW 25%, PT 35%, CP 20%, QA 20%; these are editable and are not presented as an official prescribed policy.
3. Add a manual assessment or link a published quiz/coding assignment for the selected section. Coding assignments must match the term (older tasks without a term are also available). Quizzes are assigned to a term when you link them. Each activity can be linked once per sheet. Maximum scores may be changed; linked results scale to that maximum.
4. Choose **View / grade** beside a learner. Enter scores, explicitly override a linked result, mark an item excused, or return to the linked result. Save the learner's scores and private observation together. A zero is a real score; pending is not zero.

## Calculation and live evidence

Category percentage is the sum of recorded points divided by the sum of those items' maximum scores, multiplied by 100. The sheet weights those category percentages. Pending items and excused items are excluded from the current calculation. When categories do not have scores yet, the displayed average rebalances the weights of categories that do. It is labelled **Provisional**. A complete record requires every assessment resolved and an assessment represented in each category with positive weight. An entirely excused category is excluded from the calculation.

This is a raw percentage gradebook, with no official transmutation or Form 137/138 certification. XP, welcome points, quiz speed bonuses and challenge rewards do not affect grades. No XP is changed by grade entry or import. Class average cards include provisional learner averages and say so. Three-term summaries use saved records, never predicted grades.

Linked quizzes use each student's best fully graded valid submitted attempt. Code Lab uses the latest final teacher rubric for the linked assignment, with all four 0–4 scores validated and a 16-point maximum. Unreviewed submissions and AI draft suggestions are never treated as final scores. Archived source activities retain their existing recorded results. Teachers must explicitly confirm a change in maximum; it cannot be reduced below an existing manual score.

The page polls saved records every 15 seconds while visible. Refresh pauses during an edit and rechecks after a response so that an incoming refresh cannot overwrite unsaved work. Concurrent teacher tabs are protected by a sheet revision; a stale save returns 409 and must be refreshed before retrying. Settings, assessments, scores, notes and imports are database transactions. This page does not need Node, a queue worker, or WebSockets.

## CSV and print

**Export CSV** downloads the filtered roster's category and term summaries. **Download score CSV template** includes all active learners and assessments in the selected sheet. Fill `score` and `status`, preserving IDs, then upload it. The required header is:

```csv
student_id,assessment_id,score,status
```

Statuses: `scored`, `pending` (manual items only), `excused`, `source` (linked items only). Blank scored manual items remain pending. Source status clears an override and resumes live results. Imports accept up to 2,000 rows / 2 MB, reject duplicates and foreign IDs, validate ranges and decimal precision, and roll back the entire file on an error. Template student identifiers start with an apostrophe to preserve leading zeroes in spreadsheet tools; the importer recognizes this prefix. CSV output protects spreadsheet formula prefixes.

**Classroom display: aliases** visually hides student names, IDs, the learner panel and editing tools inside the gradebook. The print and summary export links follow that display choice. It is a presentation convenience, not an access-control boundary: the signed-in teacher can still access the underlying records and score template. **Print / Save PDF** opens the browser print view with the current filters. There is no pretend Excel or official school form export.

Only the owner teacher can read or edit sheets for their current teaching sections. Students cannot access this page. Archived students remain in historical database records but leave the active roster. Scores and notes are private to the teacher; CSV summaries omit observation text. Original assessments, submissions, student accounts and XP are preserved when a gradebook assessment is removed.

## Deployment and checks

```sh
php artisan migrate --force
php artisan test --filter=GradingSheetTest
node --test tests/Frontend/grading-sheet.test.cjs
```

Node is only used for optional development checks, never to serve the application. Migration `2026_10_30_000038_create_grading_sheets.php` adds the four gradebook tables without changing existing scores. Feature tests cover section ownership, grading arithmetic, missing/zero/excused distinctions, live evidence, overrides, revision conflicts, atomic CSV import, safe export, term isolation and rendering. Browser-logic tests cover refresh/edit races, grading-method controls, alias links, print and expired sessions.
