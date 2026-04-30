<?php

declare(strict_types=1);

use App\Modules\Integrations\Github\GithubAppService;

describe('GithubAppService::verifyWebhook', function () {
    it('returns true when the signature matches the body and secret', function () {
        $service = new GithubAppService;
        $body = '{"action":"opened"}';
        $secret = 'super';
        $sig = 'sha256='.hash_hmac('sha256', $body, $secret);

        expect($service->verifyWebhook($sig, $body, $secret))->toBeTrue();
    });

    it('returns false when the signature does not match', function () {
        $service = new GithubAppService;
        expect($service->verifyWebhook('sha256=zzz', '{"action":"opened"}', 'super'))->toBeFalse();
    });

    it('returns false when the secret is empty', function () {
        $service = new GithubAppService;
        expect($service->verifyWebhook('sha256=anything', '{}', ''))->toBeFalse();
    });

    it('returns false when the signature is empty', function () {
        $service = new GithubAppService;
        expect($service->verifyWebhook('', '{}', 'super'))->toBeFalse();
    });
});

describe('GithubAppService::installUrl', function () {
    it('appends a state parameter when given', function () {
        config()->set('services.github_app.install_url', 'https://github.com/apps/foo/installations/new');

        $service = new GithubAppService;
        expect($service->installUrl('my-ws'))->toContain('state=my-ws');
    });

    it('falls back to https://github.com/apps when not configured and no app name', function () {
        config()->set('services.github_app.install_url', '');
        config()->set('services.github_app.app_name', '');

        $service = new GithubAppService;
        expect($service->installUrl())->toContain('github.com/apps');
    });
});
