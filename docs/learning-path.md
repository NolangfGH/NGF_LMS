# C++ learning path

Student page: /learn. Teacher pages: /teacher-lessons and /teacher-lesson-progress.
The application requires a signed-in account. No Node build or extra AI service is needed.

## Student workflow

1. Choose a teacher-assigned or self-study lesson. Thirteen lessons cover introduction, operators, arrays, strings, debugging, a mini-project, output,
   variables, input, decisions, for loops, sentinel while loops, and functions.
2. Read the short sections and worked example. Save a reading bookmark to resume later.
   Output shown is a teaching example, not a browser compiler result.
3. Answer three checkpoint questions and write a reflection of at least 15 characters.
   The server checks the answers. All three must be correct for checkpoint completion.
   Students can retry without a timer; latest score, best score, attempts and the first
   completion evidence are saved. A later incorrect retry does not erase completion.
4. Record confidence or ask a teacher for help. The help-only button works without
   answering the checkpoint. It does not count as an attempt or completion.
5. Open the linked Code Lab practice. An existing draft is preserved. Submit code for
   the teacher's review using the existing Code Lab workflow.

Checkpoint completion and coding application are separate indicators. A linked practice
is teacher-confirmed only after a saved review scores correctness and logic at least
3/4 each. First checkpoint completion awards 25 XP once per lesson, without changing quiz grades. A first completed checkpoint
can contribute one day toward the Learning Rhythm badge; retries do not add extra days.

## Teacher workflow

Open Lessons from the teacher dashboard, then select a lesson and one or more owned
grade/section checkboxes. Add instructions and an optional learning target in UTC+08:00.
Publish the plan or keep a draft. Saving the same lesson and section updates that plan.
Edit, unpublish, or remove individual section plans. Removing a plan keeps students'
progress; self-study remains available. Learning target dates do not lock a lesson.

Learning progress shows only students currently enrolled in the teacher's sections,
including self-study progress. Filter by section, lesson, or student name/ID. Review
latest and best checkpoint scores, attempts, reflections, confidence, help requests
and last-opened dates. Save a specific next step as feedback; it appears in the
student's lesson. Use Code Lab Reviews for practical coding assessment.

Student pages load saved data on opening or Refresh progress. Teacher reports refresh
when filters are applied. Lesson forms are submitted explicitly so reading or draft
answers are not interrupted by page reloads.

## Maintenance

Default curriculum and answer keys live in config/lessons.php; teacher-specific published content is managed at /teacher-content.
Linked practice definitions live in config/code_lab.php and config/challenge_activities.php.
After installing an updated release:

    php artisan migrate --force
    php artisan config:clear
    php artisan view:cache
    php artisan test --filter=LearningPathTest

Optional developer-only filter checks use Node's built-in test runner, without npm:

    node --test tests/Frontend/learn-filters.test.cjs

The lesson_progress table has one record per student and lesson. The
teacher_lesson_plans table has one plan per teacher, section and lesson. There are no
sample student results or automatic mastery claims. If changing checkpoint content
substantially, use a new lesson key/version and consider how historical completion
evidence should be presented before replacing the definition.
