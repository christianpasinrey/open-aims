<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'channel' => env('TELEGRAM_AIMS_CHAT_ID'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT', '/gh/callback'),
    ],

    'github_app' => [
        'app_id' => env('GITHUB_APP_ID'),
        'app_name' => env('GITHUB_APP_NAME'),
        // Used for the user-to-server OAuth flow when a workspace owner
        // connects the App to their workspace. Distinct from
        // `services.github.client_id/secret`, which are the OAuth App
        // credentials behind "Sign in with GitHub".
        'client_id' => env('GITHUB_APP_CLIENT_ID'),
        'client_secret' => env('GITHUB_APP_CLIENT_SECRET'),
        'webhook_secret' => env('GITHUB_APP_WEBHOOK_SECRET'),
        // Inline PEM (preferred for hosted deploys where you don't want
        // a file on disk). When set, takes precedence over the path.
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'private_key_path' => env('GITHUB_APP_PRIVATE_KEY_PATH', 'storage/keys/github-app.pem'),
        'install_url' => env('GITHUB_APP_INSTALL_URL'),

        // repo team key → GitHub repo full name. Hardcoded for now,
        // env-overridable per entry. Future: move to a `teams.github_repo_full_name`
        // column.
        'team_repo_map' => [
            'LAM' => env('GITHUB_APP_REPO_LAM', 'repo-lab/repo'),
        ],
    ],

];
