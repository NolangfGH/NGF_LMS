# Self-hosted C++ compiler connection

The Laravel integration is implemented. **No compiler is installed on this Windows computer, and no Judge0 server has been deployed by this change.** Docker, a Linux compiler host, and a service URL were not available in the workspace. The application does not fall back to executing student programs in PHP, PowerShell, or the shared-hosting account. Until you connect the service, Code Lab clearly shows that execution is awaiting setup; saving, static hints, downloads and teacher review remain usable.

## Separate compiler host

Use a dedicated Linux host/VM for Judge0, separate from Laravel, student records and personal files. The [official CE release instructions](https://github.com/judge0/judge0/releases/tag/v1.13.1) specify Linux, Docker/Compose and host cgroup requirements. The official release page currently labels CE 1.13.1 as latest; it fixes the critical vulnerabilities in versions 1.13.0 and older. Check the upstream release/security notices before deployment. Do not use an older image or expose an unauthenticated compiler publicly.

On that prepared Linux host, download the official CE release archive, inspect its Compose file and follow its host prerequisites:

```sh
curl -fLO https://github.com/judge0/judge0/releases/download/v1.13.1/judge0-v1.13.1.zip
unzip judge0-v1.13.1.zip
cd judge0-v1.13.1
```

Set independent random Redis and PostgreSQL passwords in the release's `judge0.conf`, and a separate authentication token. Generate secrets locally with a password manager or `openssl rand -hex 32`; do not paste them into chat. Merge the settings from `deploy/judge0/ngf-settings.conf.example`, including your Laravel server's permitted IP. This is a settings fragment, not a replacement for the official complete configuration.

Before starting the release's Compose stack, change its API port binding to `127.0.0.1:2358:2358`. Put a TLS reverse proxy in front of it, restrict the proxy/firewall to the Laravel host, and retain authentication. Keep Redis/PostgreSQL private. Do not mount the Laravel directory, the Docker socket, personal home folders or student files into the compiler. Judge0's worker sandbox requires host privileges; use a dedicated host rather than weakening isolation on your application host. Review the official host setup instead of changing cgroups or rebooting an existing shared server blindly.

After preparing that configuration, use the official Compose stack:

```sh
docker compose up -d db redis
docker compose up -d
```

Check service logs/readiness on that host. Reverse proxy access may change the IP Judge0 sees; configure its allowlist for the trusted proxy and enforce the Laravel-host restriction at the proxy/firewall. Browser clients must never call Judge0 directly or receive its token.

## Connect Laravel

Add these local values to the project's `.env` (placeholders are also in `.env.example`):

```dotenv
JUDGE0_URL=https://compiler.your-school.example
JUDGE0_AUTH_TOKEN=your-private-server-token
JUDGE0_CPP_LANGUAGE_ID=54
```

ID 54 is the common CE C++ entry; use `/languages` on your instance to confirm its C++ ID. Then run in the Laravel project:

```sh
php artisan config:clear
php artisan compiler:check
```

The check verifies connectivity and the configured C++ language; it does not certify the host's isolation. Before allowing a class to use it, run trusted smoke programs for correct output, compilation failure, an endless loop, memory exhaustion, and a denied network attempt. Verify the actual sandbox limits and logs on your service. Judge0 executes the code in its isolated worker environment; the Laravel server only sends source/input and retrieves results using the [documented batch API](https://ce.judge0.com/).

## Classroom behavior

- Students save a revision, then choose **Run C++** with custom stdin or **Check all tests**. Results include an output console, error explanations and immutable run history.
- Teacher assignments can contain eight tests with private/visible flags and positive point weights, optional starter code and five progressive hints. Partial credit is passed test points / all test points. Hidden input/output, tokens and hidden diagnostics are never returned to students.
- Only code/input are sent to Judge0. No student names, IDs, passwords, database credentials, medical documents or teacher messages are sent. Expected outputs remain in Laravel; results are compared there.
- Requests fix C++ language/options and resource limits server-side: two CPU seconds, five wall seconds, 128000 KB memory, bounded process/file output limits and network disabled. Students cannot provide callbacks, command arguments, files, language IDs or resource limits.
- One active run per student, six new runs per minute and idempotent request keys control retries. Polling retrieves existing tokens and never starts another compilation. A failed connection is reported as unavailable, never as a wrong academic answer.
- Run results are linked to the exact submitted code revision for teacher review. Automatic tests do not replace the teacher's final correctness/logic/readability/explanation rubric. A practice mission awards 30 XP on its first fully passing test suite; teacher assignments keep their existing configured one-time XP rules.
- Output comparison normalizes line endings and trims trailing whitespace, preserving interior spaces and case. Programs should print only the requested output. Service unavailability can be retried without losing saved drafts.

Shared hosting needs only PHP/Composer and outbound HTTPS to this private compiler. Node, Docker and a C++ compiler are not required on the Laravel/shared-hosting machine.
