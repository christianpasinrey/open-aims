<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Workspaces\Models\Workspace;
use App\Modules\Workspaces\Support\WorkspaceTelegram;

beforeEach(function () {
    config(['services.telegram.token' => 'TESTTOKEN', 'services.telegram.channel' => '-100GLOBAL']);
    $this->ws = Workspace::factory()->create(['owner_user_id' => User::factory()->create()->id]);
});

it('returns null when telegram is not enabled for the workspace', function () {
    expect(WorkspaceTelegram::resolveChatId($this->ws->id))->toBeNull();
});

it('returns the global channel when enabled without a chat_id override', function () {
    $this->ws->update(['settings' => ['telegram' => ['enabled' => true]]]);
    expect(WorkspaceTelegram::resolveChatId($this->ws->id))->toBe('-100GLOBAL');
});

it('returns the per-workspace chat_id override when set', function () {
    $this->ws->update(['settings' => ['telegram' => ['enabled' => true, 'chat_id' => '-100WS']]]);
    expect(WorkspaceTelegram::resolveChatId($this->ws->id))->toBe('-100WS');
});

it('returns null when the global token is missing', function () {
    config(['services.telegram.token' => null]);
    $this->ws->update(['settings' => ['telegram' => ['enabled' => true]]]);
    expect(WorkspaceTelegram::resolveChatId($this->ws->id))->toBeNull();
});

it('returns null when enabled with no chat_id and no global channel', function () {
    config(['services.telegram.channel' => null]);
    $this->ws->update(['settings' => ['telegram' => ['enabled' => true]]]);
    expect(WorkspaceTelegram::resolveChatId($this->ws->id))->toBeNull();
});
