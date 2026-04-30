<?php

declare(strict_types=1);

use App\Modules\Views\Models\IssueView;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_favourites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->string('target_type')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('label', 200);
            $table->string('icon', 64)->nullable();
            $table->string('color', 32)->nullable();
            $table->string('href', 500);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // Browse index used by the sidebar payload.
            $table->index(['user_id', 'workspace_id', 'sort_order'], 'uf_browse_idx');

            // Unique key prevents duplicate rows. We can't include `href` (500
            // chars) in a regular composite UNIQUE because of MySQL key length
            // limits, so we use a separate unique index keyed by a hash of
            // href stored in target_type/target_id when present, plus a kind +
            // workspace + user constraint. For pages with NULL target_id we
            // rely on a partial-style unique with a generated href hash via
            // the index below.
            $table->unique(
                ['user_id', 'workspace_id', 'kind', 'target_type', 'target_id'],
                'uf_target_unique',
            );
        });

        // ----------------------------------------------------------------
        // Migrate existing IssueView rows with is_favorite=true into the
        // unified user_favourites table. We keep the original is_favorite
        // column for backwards compat (the toggle endpoint mirrors writes).
        // ----------------------------------------------------------------
        if (! Schema::hasTable('issue_views')) {
            return;
        }

        IssueView::query()
            ->where('is_favorite', true)
            ->get(['id', 'name', 'workspace_id', 'owner_user_id'])
            ->each(function (IssueView $view): void {
                if ($view->owner_user_id === null || $view->workspace_id === null) {
                    return;
                }

                $exists = DB::table('user_favourites')
                    ->where('user_id', $view->owner_user_id)
                    ->where('workspace_id', $view->workspace_id)
                    ->where('kind', 'view')
                    ->where('target_type', IssueView::class)
                    ->where('target_id', $view->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('user_favourites')->insert([
                    'user_id' => $view->owner_user_id,
                    'workspace_id' => $view->workspace_id,
                    'kind' => 'view',
                    'target_type' => IssueView::class,
                    'target_id' => $view->id,
                    'label' => $view->name,
                    'icon' => 'Eye',
                    'color' => null,
                    'href' => '/views/'.$view->id,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_favourites');
    }
};
