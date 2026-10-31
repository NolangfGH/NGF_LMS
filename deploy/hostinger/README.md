# Hostinger deployment for rsmimaropa.com

The release is built from the committed Laravel application with the locked production Composer dependencies. The installed packages require PHP 8.4.1 or newer; use PHP 8.5 for this deployment. Node/npm is not needed.

## Build locally

From the project directory in PowerShell:

```powershell
.\deploy\hostinger\build.ps1
```

The generated archive is under `.codex/hostinger/release-*/`. It contains no local `.env`, database, private uploads, sessions, logs, or test results. The build preserves your local `vendor` directory and development server.

## Server layout

Extract the release one level above the site's public_html directory:

```text
/home/HOSTINGER_USER/domains/rsmimaropa.com/
├── ngf_lms/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── resources/
│   ├── routes/
│   ├── storage/
│   ├── vendor/
│   ├── artisan
│   └── .env.example
└── public_html/
    ├── .htaccess
    ├── index.php
    ├── css/
    ├── js/
    └── images/
```

Only public_html is web-accessible. Its prepared index.php loads the private sibling application and sets the HTTP public path correctly. Keep ngf_lms/public as the matching original public assets; the alternate entry point is supplied only in public_html. Do not place the complete Laravel application inside public_html. Preserve any existing site in a separate backup before switching the document root files.

## Production configuration

Create a dedicated empty MySQL database and database user in hPanel. Copy ngf_lms/.env.example to ngf_lms/.env on the server, replace the database placeholders, and retain APP_ENV=production, APP_DEBUG=false, HTTPS APP_URL, and secure session cookies. Generate APP_KEY on the server once. Keep the file readable only by your hosting account. Do not commit it to GitHub or paste credentials into chat.

From ngf_lms, using the same PHP version as the website:

```sh
php -v
composer check-platform-reqs --no-dev
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
```

For an existing production installation, preserve APP_KEY, storage, and the database; do not regenerate the key or replace the database. This first deployment uses a fresh database and does not import the local classroom records.

Use writable account-owned storage and bootstrap/cache directories; do not use world-writable permissions. The platform stores private discussion images, voice messages, and excuse documents in storage/app/private and serves them through authenticated routes. Do not expose that directory with a public storage link.

Hostinger disables proc_open, so Laravel's general schedule:run command cannot launch its child process here. The application's only scheduled task is blog response expiry. Run that command directly once per minute in hPanel, with flock preventing overlap:

```cron
* * * * * /usr/bin/flock -n /home/HOSTINGER_USER/domains/rsmimaropa.com/ngf_lms/storage/framework/blog-expiry.lock /opt/alt/php85/usr/bin/php /home/HOSTINGER_USER/domains/rsmimaropa.com/ngf_lms/artisan blog:finalize-responses >> /dev/null 2>&1
```

Choose Custom in hPanel and enter the command after the five time fields; set each time field to *. If additional scheduled tasks are introduced later, configure them explicitly or revisit scheduler support. PHP subprocess functions are not needed for this direct command or the remote Judge0 connection.

## Verify before use

Check HTTPS /up, the homepage, student login and teacher login; verify styles and branding load. Confirm /.env, /composer.json, and /ngf_lms/.env do not expose application files. Create the first teacher account at /teacher-register, create the Grade 8 sections, then verify a student can join only with a valid class code. Grant the school administrator role only to the intended account using school:promote-admin from the server terminal.

The Judge0 compiler and optional OpenAI connection remain separate configuration steps. Uploading the LMS does not create a compiler service. Do not upload local API keys automatically. Configure private server-side keys only when those integrations are ready.

Relevant references: [Hostinger SSH access](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/), [PHP settings](https://www.hostinger.com/support/1575755-how-to-change-the-php-version-of-your-hostinger-hosting-plan/), [Laravel deployment](https://laravel.com/docs/13.x/deployment).
