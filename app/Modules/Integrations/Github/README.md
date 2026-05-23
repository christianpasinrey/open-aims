# GitHub App integration

Organization-level integration that links GitHub pull requests to
aims issues by branch name. This is **not** the user-facing OAuth
flow — that lives at `app/Http/Controllers/Auth/GithubOAuthController.php`
and is independent.

## Why a GitHub App (not OAuth)

The OAuth flow is per-user and only sees what the connected user can see.
A GitHub App is installed once on a GitHub account (org or user), has its
own credentials and webhook secret, and is independent of any one user's
session. That's the right primitive for "watch all PRs in
`your-org/your-repo` and link them to issues".

## Architecture

```
app/Modules/Integrations/Github/
├── GithubAppService.php           — JWT minting, install token, REST client
├── GithubWebhookHandler.php       — signature verify + event dispatch
├── LinkPullRequestAction.php      — match a PR to issues by branch name
├── Models/
│   ├── GithubInstallation.php     — workspace ↔ installation_id mapping
│   └── GithubLinkedPullRequest.php — PR row attached to an issue
├── Http/Controllers/
│   ├── GithubAppController.php    — install flow + webhook + manual sync
│   └── GithubIntegrationSettingsController.php — /settings/github page
├── Database/Migrations/           — add columns + create tables
├── routes.php                     — registers /gh/* and /settings/github
└── README.md
```

The Integrations module's `routes.php` requires the Github sub-routes
file. Migrations are loaded explicitly from the parent
`IntegrationsServiceProvider::boot()`.

## Branch matching

Two strategies, in order:

1. **Exact match against `issues.git_branch_name`.** repo's "copy git
   branch name" feature produces strings like
   `username/key-123-issue-title...` which can be backfilled
   from `database/seed-data/{snapshot}/issues.json`.
2. **Identifier fallback** — parse `[A-Z]+-\d+` from anywhere in the
   branch name and look up by `(team.key, issue.number)`.

A PR matched against multiple issues creates one
`github_linked_pull_requests` row per match.

## Registering the App on GitHub

1. Go to <https://github.com/settings/apps> (or your org's apps page).
2. **New GitHub App.** Fill in:
    - **Name** — e.g. `your-app-name`. This becomes
      `GITHUB_APP_NAME` and is part of the install URL
      (`https://github.com/apps/your-app-name/installations/new`).
    - **Homepage URL** — your `APP_URL`.
    - **Callback URL** — `${APP_URL}/gh/install/callback`.
    - **Webhook URL** — `${APP_URL}/gh/webhook`.
    - **Webhook secret** — generate something with
      `php -r 'echo bin2hex(random_bytes(32));'` and save it as
      `GITHUB_APP_WEBHOOK_SECRET`.
3. **Permissions** (Repository):
    - Pull requests: **Read**
    - Metadata: **Read** (default)
    - Contents: **Read** (optional, useful later for fetching commit
      messages)
4. **Subscribe to events**: `Pull request`, `Installation repositories`.
5. **Where can this GitHub App be installed?** — pick "Any account" if
   you'll install it on a shared org, otherwise "Only on this account".
6. **Create**, then on the App settings page:
    - Note the **App ID** → `GITHUB_APP_ID`.
    - **Generate a private key** → save the `.pem` to
      `storage/keys/github-app.pem` (path is configurable via
      `GITHUB_APP_PRIVATE_KEY_PATH`). The directory is git-ignored.
7. **Install the App** on your-org — pick your-repo
   (or "All repositories"). After install, GitHub redirects back to
   `/gh/install/callback` and we record the installation.

## Local env

```dotenv
GITHUB_APP_ID=123456
GITHUB_APP_NAME=your-app-name
GITHUB_APP_WEBHOOK_SECRET=<32-byte hex>
GITHUB_APP_PRIVATE_KEY_PATH=storage/keys/github-app.pem
GITHUB_APP_INSTALL_URL=https://github.com/apps/your-app-name/installations/new
GITHUB_APP_REPO_LAM=your-org/your-repo
```

## Testing the webhook locally

GitHub needs a public URL. Use `cloudflared`, `ngrok`, or similar:

```bash
cloudflared tunnel --url http://localhost:8000
```

Set the App's webhook URL to the tunnel URL + `/gh/webhook`. GitHub's
"Recent Deliveries" page on the App settings has a "Redeliver" button
that's invaluable for iterating.

## Manual sync

`/settings/github` has a "Sync now" button that POSTs to `/gh/sync`. It
walks every active installation for the workspace, lists every repo
visible to it, then every PR in each repo (state=all, capped at 500),
and runs the `LinkPullRequestAction` against each.

## Caveats

- We persist a **denormalized snapshot** of each PR rather than calling
  the API on every page load. Stale data is possible if a webhook is
  missed; "Sync now" is the recovery path.
- Installation tokens are cached for 50 minutes (they expire at 60).
- The team→repo map currently lives in `config/services.php`. When we
  add multi-team / multi-repo support, move it to
  `teams.github_repo_full_name`.
