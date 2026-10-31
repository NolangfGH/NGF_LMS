# Live homepage

The public homepage is / and its update endpoint is /home/status.
The page loads current saved data on its first request, then refreshes every
15 seconds while visible. Refresh data also requests an immediate update.
Polling pauses in hidden tabs and resumes when the page becomes visible.
Failed updates preserve the last loaded values and successful-update timestamp,
show an error/offline state and retry with backoff.

## What is shown

Public visitors see current registered student and educator account totals,
created classroom sections, published activity counts and the current course
catalog. Published activity totals include saved published assignments,
challenges and quizzes, excluding drafts and soft-deleted activities. They
do not imply an activity is currently open or assigned to the visitor.

Course titles, objectives, estimated reading times, practice counts and learning
milestones come from the real configuration files. The worked example comes
from the first C++ lesson. It is presented as teaching content with expected
output, rather than a simulated compiler run.

Signed-in students additionally see their own saved XP, level, earned badge
records, completed checkpoints and submitted activities. Quiz retakes count
as the same quiz, and code revisions count as the same workspace. Reading the
homepage does not finalize quizzes, grade code, award XP or create badges.

Signed-in teachers see only their own sections, enrolled students, published
activities, latest pending code-review queue and lesson support-signal count.
No roster identities, class join codes, code, exam questions, answers or email
addresses are published to visitors.

The endpoint derives private context from server sessions. Query-string IDs
cannot select another account. Both page and JSON responses use private,
no-store cache headers. Expired session versions remove the private card on
the next successful update.

## Behavior and maintenance

- Search filters the real course library and is preserved through live updates.
- Registration, sign-in, lesson, dashboard, assignment, quiz and report buttons
  link to working application routes.
- The layout retains the existing green CodeQuest branding, with keyboard
  focus states, mobile navigation and native expandable examples.
- The old placeholder mission-generation form, fabricated social proof,
  sample roster and unsupported compiler claims are removed from the page.
  The older /generate endpoint is retained for compatibility.
- Missing database data produces unknown indicators and an unavailable message,
  rather than invented zero counts or exposed SQL errors.
- Updates are rate-limited per browser session and IP so classmates sharing a
  school network do not consume a single update allowance.

No WebSocket process, Node build, external API or scheduled job is required.
The application uses its existing PHP server and Tailwind CDN assets.

## Checks

    php artisan test --filter=HomepageLiveTest
    node --test tests/Frontend/home.test.cjs
    php artisan view:cache
