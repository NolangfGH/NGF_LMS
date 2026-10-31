# AI-assisted Code Lab reviews

On Code Lab Reviews, pending submissions automatically load suggested scores
for correctness, logic, readability and explanation (0–4 each), rationale, and
editable feedback. The teacher can change all four scores and the feedback.
Only the owning teacher's Save feedback & learning score action publishes
the final review. AI generation never finalizes a review or awards XP.

## Enable OpenAI

Set these values in the project's private .env file:

    OPENAI_API_KEY=your_api_key
    OPENAI_MODEL=gpt-4.1-mini

Then run:

    php artisan config:clear

Keep the key out of Blade, JavaScript, screenshots, and version control.
The key needs access to the selected model and available API quota.
The application uses Laravel's HTTP client with a 25-second timeout: no npm,
Node server, worker, new Composer package, or server C++ compiler is required.
Requests use the OpenAI Responses API and a strict JSON schema:
[official Structured Outputs guide](https://developers.openai.com/api/docs/guides/structured-outputs).
OPENAI_MODEL can select another model supporting Responses and Structured Outputs.

## Review behavior

- Suggestions are cached against the immutable submission revision. Refreshing
  the page or choosing Use saved AI suggestions reuses the saved draft.
- Automatic loading preserves teacher edits made while AI is responding.
- Each category has an AI explanation, and the displayed total reflects the
  teacher's current selections.
- A missing key, provider error, refusal, timeout, or invalid AI output does not
  block the manual rubric. Failed generation never changes an existing review.
- The AI endpoint and final-save endpoint both check the submission's owning
  teacher. Students and other teachers cannot generate or finalize reviews.
- The final reviewer account is stored in reviewed_by_teacher_id. The original
  AI suggestion remains separate for comparison with the teacher's decision.
- Existing XP rules are unchanged; generating suggestions cannot credit XP.

## Submitted content

Only task title, the snapshotted objective when available, sample output,
submitted C++ source, and student explanation are sent to OpenAI. Student
account/profile fields and private excuse evidence are excluded. Code and
explanations can still contain whatever students type into them.
Requests set store=false and include no execution tools.

New submissions snapshot their original objective. Older submissions may have
no recorded objective; the AI identifies missing context rather than inventing
requirements or penalizing missing teacher context.
Student code/comments are untrusted assessment material, not model
instructions. Outputs are validated and displayed as plain text.

This is static AI assessment, not compilation or a verified test result.
Teachers should check suggestions against the task and run suitable tests in
their C++ compiler before saving the final learning score.

## Verification

    php artisan test --compact --filter=AiLabReviewTest

Provider responses are mocked in tests. An actual API call requires a configured
key; no live model grading quality is claimed by the automated tests.
