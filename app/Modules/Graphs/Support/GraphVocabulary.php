<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

/**
 * Closed vocabularies of the graph schema. Kept closed on purpose: queries
 * such as "which controllers write this column" only work when every graph
 * uses the same words.
 */
final class GraphVocabulary
{
    public const STAGES = ['planned', 'implemented', 'observed'];

    public const CONTEXTS = ['backend', 'frontend', 'tests', 'database', 'infra', 'config', 'docs', 'other'];

    public const ROLES = [
        'model', 'controller', 'action', 'service', 'mcp_tool', 'job', 'listener', 'observer', 'event',
        'policy', 'request', 'middleware', 'migration', 'seeder', 'factory', 'route', 'config', 'provider',
        'command', 'notification', 'resource', 'component', 'page', 'composable', 'layout', 'store',
        'util', 'type', 'style', 'test', 'doc', 'other',
    ];

    public const CHANGES = ['added', 'modified', 'removed', 'read'];

    public const CLASS_TYPES = ['class', 'interface', 'trait', 'enum', 'component', 'module', 'other'];

    public const VISIBILITIES = ['public', 'protected', 'private', 'exported', 'internal'];

    public const SIDE_EFFECTS = ['db_read', 'db_write', 'http', 'queue', 'event', 'cache', 'filesystem', 'mail', 'notification'];

    public const RESOURCE_TYPES = ['table', 'column', 'route', 'event', 'queue', 'env', 'mcp_tool', 'config_key', 'external_api', 'cache_key'];

    /** Directed: `from` does the verb to `to`. */
    public const RELATIONS = [
        'calls', 'reads', 'writes', 'dispatches', 'listens', 'renders', 'extends', 'implements',
        'uses', 'tests', 'routes_to', 'migrates',
    ];

    /** @var array<string,string> */
    private const LANGUAGES = [
        'php' => 'php',
        'vue' => 'vue',
        'ts' => 'typescript',
        'tsx' => 'typescript',
        'js' => 'javascript',
        'mjs' => 'javascript',
        'css' => 'css',
        'json' => 'json',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'md' => 'markdown',
        'sql' => 'sql',
        'sh' => 'shell',
        'neon' => 'neon',
        'xml' => 'xml',
    ];

    public static function languageOf(string $file): ?string
    {
        if (str_ends_with($file, '.blade.php')) {
            return 'blade';
        }

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        return self::LANGUAGES[$extension] ?? null;
    }
}
