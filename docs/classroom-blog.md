# Classroom blog

- Teacher workspace: `/teacher-blog` (or **Blog** in the admin menu).
- Student library: `/blog` (or **Blog** in the student menu).

## Publish a topic

Choose **Write an article**, enter a title and topic, then add article text, an image, or both. Select **Draft** to keep it private or **Published** to share it with students across all sections belonging to your teacher account. Articles support plain text and C++ code, including indentation. Search and category filters use saved articles.

You can edit an article or return it to draft. **Archive** removes it from the blog while retaining its database record, images, and private discussions. There is no permanent deletion button.

## Private student conversations

Students open an article and send an insight, question, image, or a combination. Each opening comment starts a private conversation. The student and the article teacher can each add **up to 10 responses** to that conversation, with text, an image, or both. The student's opening comment does not count toward those 10 responses. Each person has an independent allowance and can continue while the other person has reached their limit. Counts and remaining responses are displayed beside each discussion.

Responses are added to a chronological history. Earlier messages and images stay available. Existing teacher replies are carried into the history during the upgrade and count as the teacher's first response. The limit is enforced by the server, including repeated requests and submissions from multiple tabs.

A student sees only their own conversations and the article teacher's responses to them. The article teacher sees every conversation on their own articles. Other teachers cannot manage those discussions. Conversations return to the teacher's waiting count when a student adds a follow-up response and the teacher has a response remaining. Once both people use their allowance, the discussion is marked complete and remains readable.

Discussion panels refresh every 15 seconds while the page is visible. Refresh pauses if an unsent teacher or student response is being edited. New student comment drafts stay outside the refreshed panel. Pagination supports longer discussions.

## Graded articles and XP

The teacher's article form includes **Article assessment** (graded or ungraded) and a **Student response time limit** in minutes (1–60; leave blank for no timer). New articles suggest 5 minutes. Existing articles remain ungraded and untimed until the teacher changes them.

On a graded article, **every student has a separate grading allowance**: their own first and second comments can earn points, up to 10 XP per student per article. This applies to every enrolled student, regardless of when other students commented. Each private discussion displays the student's personal XP total and the teacher's assessment form identifies that student by name.

The opening comment counts as that student's first message; their next follow-up or another opening comment can be their second. Teacher replies and other students' comments do not use this allowance. Starting another conversation does not reset it. Later messages remain useful for discussion but earn no XP. If a student's first comment is totally correct and earns 10 XP, their second comment remains available for discussion but earns no additional XP. Other students keep their own grading opportunities.

Assessments award 10 XP for totally correct, 5 XP for semi correct, 3 XP for slightly correct, or 0 XP for not correct/no answer. A student can earn **at most 10 XP per article**. Assess the first message before the second. A totally correct first message ends XP grading for that article. For example, 5 + 3 earns 8 XP; 3 + 10 earns 10 XP, with only 7 additional XP credited on the second message. Saved assessments are final; the teacher can send further feedback in the conversation. Repeated grade requests do not award duplicate XP.

XP updates the student's existing total and level, so it is included in the leaderboard. Grading controls are available only to the article's teacher. Students see the assessment, learning feedback and credited XP privately.

## Timed response boxes

Students press **Start response** before typing on a timed article. A decreasing bar and time label show the remaining time. Only one timed response per student per article can be active. Refreshing, opening a second tab or changing the teacher's configured time limit does not reset an active deadline.

Text drafts save while the student writes. Image and voice attachments save after being selected or recorded. The browser freezes and sends the current draft when the timer reaches zero, stopping an active recording first. The server allows a fixed three-second transport window for that final submission. Later edits are ignored; the last saved draft is submitted instead. A submission is recorded once, even if a request is retried. Empty expired responses are recorded as unanswered.

Expired drafts are also finalized when the teacher or student opens the blog. To finalize them in the background while every browser is closed, run the normal Laravel scheduler. Locally: `php artisan schedule:work`. On shared hosting, configure a cron entry once per minute using the host's compatible PHP binary:

```cron
* * * * * /path/to/php "/path/to/NF_CodeQuest C++/artisan" schedule:run
```

The scheduled command is `blog:finalize-responses`. It can also be run manually. A connection is needed to save or send a draft; keep the page open if a network error is shown. Only content already received by the server can be recovered after the browser is closed.

## Private voice messages

Students and teachers can record a voice message (up to 90 seconds), listen to it, remove it or upload an existing WebM, Ogg, MP3, M4A/MP4 or WAV clip up to 2 MB. Text, images and voice can be combined. Recording begins only when the user clicks **Record voice** and grants microphone access. The microphone is released when recording stops or the page closes. Browser recording requires a supported browser and a secure context such as HTTPS or localhost; file upload remains available when microphone recording is unavailable. See [MDN's microphone API documentation](https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia).

Voice files use private storage and authenticated playback routes. Only the comment's student and the article teacher can play the submitted recordings. Draft attachments remain visible only to their student until submitted. Audio uploads are validated by their actual file contents and allowed container types.

## Images and access

JPEG, PNG and WebP images are accepted up to **2 MB** and **6000 × 6000 pixels**, matching the current local PHP upload limit. Selected images have a preview. After a form validation error, choose the image again before submitting.

Uploads are stored under `storage/app/private/blog/`, outside the public web directory. The application checks the signed-in role, article owner and comment author before serving each image. Comment and reply images cannot be opened by classmates, even with a copied link. Responses use `Cache-Control: private, no-store`.

Teacher and student routes remain separate even if both accounts are signed in within one browser. Article and discussion text is escaped when displayed. Archived student accounts keep their comments for teacher review but cannot sign in.

## Deployment

Run `php artisan migrate --force` after deploying the code. Ensure `storage/` and `bootstrap/cache/` are writable by PHP. The blog uses the existing MySQL/SQLite connection, local private storage, Tailwind CDN and ordinary JavaScript. No Node.js server, public storage link, queue worker or WebSocket service is required.

Privacy, publication and article management are covered by `tests/Feature/BlogTest.php`. Conversation limits and upgrades are covered by `BlogConversationTest.php`; grading, XP, timers, draft expiry and voice privacy by `BlogLearningTest.php`. Browser logic tests use Node's built-in runner: `node --test tests/Frontend/blog-activity.test.cjs`. Node.js is not needed to run or deploy the application.
