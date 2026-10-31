# Learning achievements and challenges

## Students

- Achievements shows sixteen learning milestones with requirements, progress,
  earned dates, and saved evidence. A learner can choose one personal focus.
- Badges are synchronized from existing quiz results, submitted coding work,
  and final teacher rubric scores. They do not change XP.
- Quiz attempts/final opportunities count as one quiz; coding revisions count
  as one activity. Learning days use Asia/Singapore and need not be consecutive.
- Skill badges use final teacher scores, never an AI suggestion alone.
- Feedback Builder requires a higher-scored revision submitted after earlier
  teacher feedback. Saved badge evidence remains part of the learner's history.
- Pending review and progress state are shown without invented mastery/streaks.
- Challenges includes six guided missions: output, variables, conditions, loops,
  functions, and sentinel input. These are practice activities, not fake teacher
  assignments. Each has an objective, sample output, and planning/test hints.
- Students work in Code Lab: drafts save to their account and submissions go to
  teacher review. Existing work is resumed without overwriting source code.
- Teacher challenge completion requires a final review with correctness and
  logic each at least 3/4. A pending AI draft cannot confirm completion.

## Teachers

- Challenge Studio publishes problems to selected grade sections. Published
  challenges require an objective and sample output. Drafts stay private.
- Existing assignment controls provide school term, difficulty, XP, deadline,
  allotted time, editing, deletion, and individual final-opportunity requests.
- Challenges are kept separate from the ordinary student assignment list.
- Code Lab Reviews can filter teacher challenges, assignments, or guided practice.
- Student Achievements shows the teacher's own enrolled learners and their
  badge evidence, current goal, review needs, and activity counts.
- Filter by section or nickname/ID, and export the selection as CSV.
  Export cells are escaped to prevent spreadsheet formula execution.

## Reward and evidence rules

Opening a teacher activity grants its configured opening XP once, following
the existing class rule. Guided practice does not grant opening XP. Badges
recognize evidence rather than grant another XP payment.

Teacher scores describe reviewed performance. Source hints and AI drafts are
not compilation or executed tests; teacher review remains the final decision.

Existing records can be synchronized without changing XP:

    php artisan achievements:sync

Normal quiz completion, Code Lab submission/final review, student pages, and
the teacher report synchronize applicable milestones automatically. Unique
student/badge records and student locks prevent duplicate awards.

Verification:

    php artisan test --compact --filter='AchievementsTest|ChallengesTest'

See platform-features.md for the extended quest map, executed-test badges, seven-day streak and once-only lesson/practice XP rewards. See self-hosted-compiler.md for actual C++ execution setup.
