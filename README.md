# AIMS — Advanced Issue Management System

A self-hosted, keyboard-driven issue tracker for managing issues, projects, and cycles. Built as a fast workspace for small teams that want to run their own planning tool on their own infrastructure.

## Stack

- **Laravel 12** + PHP 8.4 — modular monolith (`app/Modules/*`)
- **Vue 3.5** + **Inertia 2** — single-page client, server-driven routing
- **Tailwind CSS v4** + **shadcn-vue** + **Reka UI** — design system
- **Vite** + **Wayfinder** — typed routes generated from Laravel
- **MySQL** — primary store
- **Pest / PHPUnit** — backend tests

## Local setup

Requires PHP 8.4, Composer, Node 20+, and MySQL 8.

```bash
# 1. Install dependencies
composer install
npm install

# 2. Configure environment
cp .env.example .env
php artisan key:generate

# 3. Edit .env — at minimum set DB_DATABASE, DB_USERNAME, DB_PASSWORD
# Create the database, then:
php artisan migrate --seed

# 4. Run dev server
composer dev   # runs PHP server, queue worker, Vite, and log tail concurrently
# OR
php artisan serve
npm run dev
```

The app is now reachable at `http://localhost:8000`.

## GitHub integration

AIMS supports two GitHub flows:

### 1. OAuth — sign-in and account linking

Lets users sign in with GitHub and link their account to surface their PRs in issues.

In `.env`:

```
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT="${APP_URL}/gh/callback"
```

Create an OAuth App at <https://github.com/settings/developers>:

- Homepage URL: `${APP_URL}`
- Authorization callback URL: `${APP_URL}/gh/callback`

### 2. GitHub App — webhook ingestion

Connects a repo (or org) so that PR / commit references like `LAM-123` auto-link to the matching AIMS issue.

In `.env`:

```
GITHUB_APP_ID=
GITHUB_APP_NAME=
GITHUB_APP_WEBHOOK_SECRET=
GITHUB_APP_PRIVATE_KEY_PATH=storage/keys/github-app.pem
GITHUB_APP_INSTALL_URL=https://github.com/apps/<your-app-name>/installations/new
```

Create the GitHub App at <https://github.com/settings/apps/new>:

- Webhook URL: `${APP_URL}/gh/webhook`
- Webhook secret: same as `GITHUB_APP_WEBHOOK_SECRET`
- Permissions: `Pull requests: read`, `Contents: read`, `Issues: read`, `Metadata: read`
- Subscribe to events: `Pull request`, `Push`, `Issues`
- Download the private key and save to `storage/keys/github-app.pem`

Map a repository to a workspace via env (one variable per workspace), e.g.:

```
GITHUB_APP_REPO_LAM=your-org/your-repo
```

Install the App on the configured repo using `GITHUB_APP_INSTALL_URL`. PRs and pushes that reference an issue key (e.g. `LAM-42` in title, branch, or commit message) will be auto-linked.

## Keyboard shortcuts

The workspace is keyboard-first.

| Shortcut | Action |
|---|---|
| `Ctrl+K` / `Cmd+K` | Open the search palette |
| `G` then `I` | Go to issues |
| `G` then `P` | Go to projects |
| `G` then `C` | Go to cycles |
| `G` then `H` | Go home |
| `C` | Create a new issue (when not typing in a field) |
| `?` | Show the shortcuts cheat sheet |

## Useful commands

```bash
# Run tests
php artisan test

# Format PHP
vendor/bin/pint

# Check / fix JS+Vue
npm run lint
npm run format
npm run types:check

# Production build
npm run build
```

## Project layout

```
app/
  Models/                       Eloquent models (User, Workspace, Team, Issue, ...)
  Modules/
    Issues/                     Issues domain (controllers, resources, requests)
    Projects/                   Projects + milestones
    Cycles/                     Cycle planning + transitions
    Workspace/                  Settings, members, invitations
    Integrations/
      Github/                   OAuth, App service, webhook handler
resources/js/
  pages/                        Inertia pages — one per route
  components/repo/            Atomic issue-tracker components (StatusIcon, ProjectIcon, ...)
  components/ui/                shadcn-vue primitives
  composables/                  Reusable logic (keyboard shortcuts, theme, ...)
  layouts/                      Inertia persistent layouts
routes/
  web.php                       Public + authenticated routes
  settings.php                  /settings/* routes
```

## License

Private — do not redistribute.
