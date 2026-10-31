# Classroom notifications

The student dashboard and teacher dashboard now share an in-app notification inbox. It also appears on the existing learning, blog, settings and review pages that previously displayed challenge notifications. The green theme, scrollable categories and collapsible list fit the existing dashboards.

## Who receives each update

| Event | Recipient |
| --- | --- |
| An assignment or whole-section coding challenge is published | Active students in the selected sections |
| A quiz is published, including a future scheduled quiz | Active students in its selected sections; open the notice to check the schedule |
| A blog article is published | Active students across the article teacher's sections |
| A student posts a blog comment or follow-up, including image/voice responses | The article's teacher |
| A teacher replies in a private blog conversation | Only the student who owns that conversation |
| A blog assessment is saved | Only that student |
| Student code is submitted for review | The submission's teacher |
| A student finishes a quiz, including a timed submission | The quiz's teacher |
| An assigned lesson is completed | The classroom's teacher |
| A code review is saved | Only the submitting student |
| A paired coding challenge is invited, accepted, submitted, reviewed or awarded | The original participants and teacher, following existing challenge rules |

Draft assignments, quizzes and articles are not announced to students. Publishing later sends the announcement. Re-saving the same publication or reprocessing a submission does not create duplicates. Adding another section sends the publication notice to newly targeted students.

Private blog replies do **not** notify other students. Notifications contain a brief description and an authorized link, not private comment bodies, answers, code, scores, medical records or voice/image files. Students cannot read or mark another account's notifications. Student-facing routes stay within the student role even when the browser also has a teacher session.

## Inbox controls

- Unread total and unread counts for Assignments, Quizzes, Code challenges, Blog & articles, Teacher feedback, and Lessons.
- Choose a category, enable **Unread only**, open an update, or mark a single item as read.
- **Mark all current notifications as read** uses the last fetched notification ID. A newer update arriving during that action remains unread.
- Twenty items per page; **Load older notifications** keeps older history available. Use Refresh to return to the latest page.
- The inbox refreshes every eight seconds while the page is visible. It stops requesting updates after the signed-in session ends and clears the old inbox from the page.
- Unanswered coding challenge invitations remain visible even after their notification has been read.

Opening an update marks it read. It does not submit, accept, start a timer, grade or complete the underlying task. When an item has been deleted, unpublished or is no longer accessible, the entry is retained with an unavailable indication.

## Existing work and automatic submissions

The first inbox visit imports relevant existing published work and saved submissions/replies. Later visits use the persisted inbox. New students receive notices for work already assigned to their section. Existing challenge messages and read states are retained in the same table. Moving a student to another classroom refreshes their relevant initial history without duplicating prior notices.

Blog timers continue using their existing browser submission and scheduled finalization. Dashboard notification requests also finalize expired saved blog drafts within that account's authorized scope, so the teacher can receive an update while remaining on the dashboard. The Laravel scheduler remains useful when no user has any page open.

These are **in-app** notifications. There are no email, SMS, external messages or browser push subscriptions. No Node.js process, WebSocket server or queue worker is required. Existing quiet-mode/settings summary-card preferences retain their previous behavior; the activity inbox itself keeps the core school updates available.

## Implementation and deployment

Run the standard Laravel migrations after deployment. Migration `2026_10_30_000037_expand_classroom_notifications.php` extends the existing `challenge_notices` table and adds one-time history initialization markers. No existing challenge messages are reset.

Publication hooks run after section assignments have been synchronized. Blog comments, responses, grades, code submissions and lesson completions use model observers; quiz completion connects to its existing finalization service. Notifications are saved within the same database transaction as those actions where the existing workflow is transactional. Delivery keys prevent repeated notices.

`LearningNotificationsTest.php` covers audience targeting, history, privacy, completion events, pagination and read ownership. `tests/Frontend/learning-notifications.test.cjs` covers the inbox controls, safe text rendering, URL checks, read-state races and ended sessions.
