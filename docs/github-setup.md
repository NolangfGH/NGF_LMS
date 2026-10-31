# GitHub and local development

Repository: [NolangfGH/NGF_LMS](https://github.com/NolangfGH/NGF_LMS). The configured remote is `origin`, and the local branch is `main`.

The GitHub repository stores the application source, migrations, tests, public branding assets, and documentation. Keep the repository private unless you deliberately choose to publish the source.

Your local `.env`, application keys, API credentials, student database, private uploads, sessions, logs, and generated test files are excluded by `.gitignore`. GitHub is not a backup of classroom records; back up those separately to a private destination.

## Save future changes

Open PowerShell in the project folder:

```powershell
git status
git add --all
git diff --cached --stat
git commit -m "Describe the changes you made"
git push
```

Review the staged files before committing. Keep real credentials in your local `.env`; `.env.example` contains placeholders only. Future edits are uploaded when you commit and push them.

## Set up a fresh clone

Use the repository's **Code > HTTPS** address with `git clone`, then open the cloned folder:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
```

Configure the database credentials in `.env` before running:

```powershell
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Do not overwrite an existing `.env` or database when updating an existing installation. No npm or Vite build is required. The isolated C++ compiler is configured separately; see [self-hosted compiler setup](self-hosted-compiler.md).

Uploading the source to GitHub does not deploy the website or make the local development server publicly accessible.
