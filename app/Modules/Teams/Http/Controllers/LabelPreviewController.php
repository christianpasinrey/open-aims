<?php

declare(strict_types=1);

namespace App\Modules\Teams\Http\Controllers;

use App\Modules\Teams\Models\Label;
use App\Modules\Workspaces\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class LabelPreviewController
{
    public function show(int $id): JsonResponse
    {
        $workspace = app()->bound('current.workspace') ? app('current.workspace') : null;
        if (! $workspace instanceof Workspace) {
            throw new NotFoundHttpException('No active workspace.');
        }

        $label = Label::query()
            ->where('id', $id)
            ->whereHas('team', static function ($q) use ($workspace): void {
                $q->where('workspace_id', $workspace->id);
            })
            ->with('team:id,name,key,workspace_id')
            ->first();

        if ($label === null) {
            throw new NotFoundHttpException('Label not found.');
        }

        $issueCount = (int) DB::table('issue_label')
            ->where('label_id', $label->id)
            ->count();

        return response()->json([
            'id' => $label->id,
            'name' => $label->name,
            'color' => $label->color,
            'description' => $label->description,
            'team' => $label->team ? [
                'id' => $label->team->id,
                'name' => $label->team->name,
                'key' => $label->team->key,
            ] : null,
            'issues' => ['total' => $issueCount],
        ]);
    }
}
