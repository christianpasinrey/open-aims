<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Support;

use App\Modules\Graphs\Actions\IngestGraph;

/**
 * The graph schema explained for MCP clients: every field, every vocabulary
 * value and what it means, plus a payload that IngestGraph accepts as is.
 * GraphVocabulary stays the source of the allowed values; a test keeps the
 * definitions here in sync with it.
 */
final class GraphSchema
{
    public const TOPICS = ['all', 'graph', 'reference', 'function', 'resource', 'edge', 'keys', 'vocabulary', 'relations', 'example'];

    public const STAGES = [
        'planned' => 'Written before coding, from the plan. Use it with graphs-impact to see what the change would affect.',
        'implemented' => 'Written when the work is done, describing what was really touched. Set ref to the merge commit or branch.',
        'observed' => 'Documents existing code that this work read or explored without changing its behaviour.',
    ];

    public const CONTEXTS = [
        'backend' => 'Server-side application code: models, controllers, actions, jobs, MCP tools.',
        'frontend' => 'Browser code: pages, components, composables, styles.',
        'tests' => 'Automated tests and their fixtures.',
        'database' => 'Migrations, seeders, factories and schema-level resources.',
        'infra' => 'Deployment, CI, queues, workers, server configuration.',
        'config' => 'Application configuration files and keys.',
        'docs' => 'Documentation, guides and READMEs.',
        'other' => 'Anything that fits none of the above. Prefer a specific context.',
    ];

    public const ROLES = [
        'model' => 'Persistence entity (Eloquent model).',
        'controller' => 'HTTP entry point that renders a page or returns JSON.',
        'action' => 'Single use-case class invoked by controllers, tools or jobs.',
        'service' => 'Orchestrates several operations or talks to external systems.',
        'mcp_tool' => 'Tool exposed through the MCP server.',
        'job' => 'Queued background work.',
        'listener' => 'Reacts to a dispatched event.',
        'observer' => 'Reacts to model lifecycle events.',
        'event' => 'Event class that others listen to.',
        'policy' => 'Authorization rules.',
        'request' => 'Form request / input validation.',
        'middleware' => 'HTTP middleware.',
        'migration' => 'Database schema change.',
        'seeder' => 'Seeds data.',
        'factory' => 'Test data factory.',
        'route' => 'Route definition file.',
        'config' => 'Configuration file.',
        'provider' => 'Service provider / bootstrapping.',
        'command' => 'Console command.',
        'notification' => 'Notification sent through mail, database, push or chat.',
        'resource' => 'API resource / serializer or MCP resource.',
        'component' => 'Reusable UI component.',
        'page' => 'Route-level UI page.',
        'composable' => 'Reusable frontend logic (Vue composable, hook).',
        'layout' => 'Page layout wrapper.',
        'store' => 'Client-side state store.',
        'util' => 'Small pure helper.',
        'type' => 'Type or interface definitions only.',
        'style' => 'Stylesheet.',
        'test' => 'Test case.',
        'doc' => 'Documentation file.',
        'other' => 'Anything that fits none of the above. Prefer a specific role.',
    ];

    public const CHANGES = [
        'added' => 'Created by this work.',
        'modified' => 'Behaviour or signature changed by this work. Drives impact analysis.',
        'removed' => 'Deleted by this work. Everything depending on it is affected.',
        'read' => 'Used or studied but not changed. Never produces impact.',
    ];

    public const CLASS_TYPES = [
        'class' => 'Concrete or abstract class.',
        'interface' => 'Contract without implementation.',
        'trait' => 'Reusable set of methods mixed into classes.',
        'enum' => 'Enumeration.',
        'component' => 'UI component defined as a class-like unit (e.g. a Vue single-file component).',
        'module' => 'File-level module with exports but no class.',
        'other' => 'Anything else class-like.',
    ];

    public const VISIBILITIES = [
        'public' => 'Callable from anywhere. Signature changes affect every caller.',
        'protected' => 'Callable from the class and its subclasses.',
        'private' => 'Callable only inside the class. Changes stay local.',
        'exported' => 'Exported from a JS/TS module. Behaves like public.',
        'internal' => 'Module-private function in JS/TS. Behaves like private.',
    ];

    public const SIDE_EFFECTS = [
        'db_read' => 'Reads from the database.',
        'db_write' => 'Writes to the database.',
        'http' => 'Performs an HTTP request.',
        'queue' => 'Dispatches a queued job.',
        'event' => 'Dispatches an event.',
        'cache' => 'Reads or writes the cache.',
        'filesystem' => 'Reads or writes files.',
        'mail' => 'Sends email.',
        'notification' => 'Sends a notification (push, Telegram, database).',
    ];

    public const RESOURCE_TYPES = [
        'table' => 'Database table; name is the table name.',
        'column' => 'Database column; name is "table.column".',
        'route' => 'HTTP route; name is "METHOD /path/{param}".',
        'event' => 'Domain or framework event; name is the event name or class.',
        'queue' => 'Queue or connection name.',
        'env' => 'Environment variable name.',
        'mcp_tool' => 'MCP tool name, e.g. "issues-update".',
        'config_key' => 'Configuration key, e.g. "mcp.redirect_domains".',
        'external_api' => 'Third-party API; name is the service and endpoint.',
        'cache_key' => 'Cache key or key pattern.',
    ];

    public const RELATIONS = [
        'calls' => 'from invokes to (function or method call).',
        'reads' => 'from reads data held by to (table, column, config, cache, file).',
        'writes' => 'from writes data held by to.',
        'dispatches' => 'from dispatches the event or job to.',
        'listens' => 'from handles the event to.',
        'renders' => 'from renders the page or component to.',
        'extends' => 'from inherits from to.',
        'implements' => 'from implements the contract to.',
        'uses' => 'from depends on to without calling it directly (imports a trait, type, constant).',
        'tests' => 'from is a test that covers to.',
        'routes_to' => 'from is a route that points at the controller or action to.',
        'migrates' => 'from is a migration that changes the table or column to.',
    ];

    /**
     * @return array<string,mixed>
     */
    public static function topic(string $topic): array
    {
        return match ($topic) {
            'graph' => ['graph' => self::graph()],
            'reference' => ['reference' => self::reference()],
            'function' => ['function' => self::function()],
            'resource' => ['resource' => self::resource()],
            'edge' => ['edge' => self::edge()],
            'keys' => ['keys' => self::keys()],
            'vocabulary' => ['vocabulary' => self::vocabulary()],
            'relations' => ['relations' => self::RELATIONS],
            'example' => ['example' => self::example()],
            default => [
                'graph' => self::graph(),
                'reference' => self::reference(),
                'function' => self::function(),
                'resource' => self::resource(),
                'edge' => self::edge(),
                'keys' => self::keys(),
                'vocabulary' => self::vocabulary(),
                'limits' => self::limits(),
                'example' => self::example(),
            ],
        };
    }

    /** @return array<string,mixed> */
    public static function graph(): array
    {
        return [
            'owner' => [
                'issue' => 'owner = "TEAMKEY-N"',
                'project' => 'owner = project slug',
                'milestone' => 'owner = milestone id, or its name together with project_slug',
            ],
            'required' => [
                'title' => 'Unique per owner. Sending the same title again replaces the graph and bumps its version.',
                'summary' => 'What this work changes, in one or two sentences.',
                'stage' => 'planned | implemented | observed (see vocabulary.stages).',
                'repo' => 'Repository prefix of every node key, lowercase, e.g. "open-aims".',
                'ref' => 'Branch or commit the references point at.',
                'references' => 'At least one code reference.',
            ],
            'optional' => [
                'resources' => 'Non-code nodes: tables, columns, routes, events, queues, env, MCP tools…',
                'edges' => 'Directed relations between references, functions, resources or existing keys.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function reference(): array
    {
        return [
            'required' => [
                'id' => 'Local id used by edges, letters, digits, "_", "-", "." (no colon).',
                'file' => 'Path relative to the repository root with "/", no leading "/", "..", "#" or ":".',
                'context' => 'See vocabulary.contexts.',
                'role' => 'See vocabulary.roles.',
                'change' => 'See vocabulary.changes. Functions inherit it unless they set their own.',
                'description' => 'What the file or class is.',
                'purpose' => 'Why it exists in the system.',
                'summary' => 'What this work does to it.',
                'functions' => 'List of functions involved (may be empty). See function.',
            ],
            'required_with_class' => [
                'namespace' => 'Namespace of the class (PHP namespace, or the folder for components).',
                'class_type' => 'See vocabulary.class_types.',
            ],
            'optional' => [
                'class' => 'Class, trait, interface, enum or component name. Makes the reference class-level.',
                'extends' => 'Parent class name.',
                'implements' => 'List of implemented contracts.',
                'language' => 'Deduced from the extension when omitted.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function function(): array
    {
        return [
            'required' => [
                'name' => 'Function or method name.',
                'signature' => 'Full signature with parameter and return types, e.g. "handle(Request $request): Response".',
                'visibility' => 'See vocabulary.visibilities.',
                'summary' => 'What the function does in this work.',
            ],
            'optional' => [
                'change' => 'Overrides the reference change for this function.',
                'lines' => '[start, end] line numbers at ref.',
                'side_effects' => 'List from vocabulary.side_effects.',
                'throws' => 'Exception classes it can throw.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function resource(): array
    {
        return [
            'required' => [
                'id' => 'Local id used by edges.',
                'type' => 'See vocabulary.resource_types.',
                'name' => 'Name following the convention of its type.',
                'description' => 'What the resource holds or does.',
                'change' => 'See vocabulary.changes.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function edge(): array
    {
        return [
            'required' => [
                'from' => 'Endpoint doing the verb.',
                'to' => 'Endpoint receiving it.',
                'relation' => 'See relations.',
            ],
            'optional' => ['label' => 'Short free text shown on the edge.'],
            'endpoints' => [
                'reference id' => 'The class of the reference, or its file when it has no class.',
                'reference id#function' => 'A function listed in that reference.',
                'resource id' => 'A resource of this graph.',
                'canonical key' => 'Any node that already exists in the workspace (see keys). Links this graph to others.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function keys(): array
    {
        return [
            'file' => '{repo}:{file}',
            'class' => '{repo}:{file}#{Class}',
            'function' => '{repo}:{file}#{Class}::{function}  or  {repo}:{file}#{function} without class',
            'resource' => '{repo}:resource:{type}:{name}',
            'hierarchy' => 'Functions hang from their class (or file) and classes from their file automatically; do not add edges for containment.',
            'merge' => 'Two graphs that produce the same key share the node. That is how the workspace map is built.',
            'examples' => [
                'open-aims:app/Modules/Issues/Models/Issue.php',
                'open-aims:app/Modules/Issues/Models/Issue.php#Issue',
                'open-aims:app/Modules/Issues/Models/Issue.php#Issue::milestone',
                'open-aims:resources/js/composables/useAppearance.ts#updateTheme',
                'open-aims:resource:column:issues.project_milestone_id',
            ],
        ];
    }

    /** @return array<string,array<string,string>> */
    public static function vocabulary(): array
    {
        return [
            'stages' => self::STAGES,
            'contexts' => self::CONTEXTS,
            'roles' => self::ROLES,
            'changes' => self::CHANGES,
            'class_types' => self::CLASS_TYPES,
            'visibilities' => self::VISIBILITIES,
            'side_effects' => self::SIDE_EFFECTS,
            'resource_types' => self::RESOURCE_TYPES,
            'relations' => self::RELATIONS,
        ];
    }

    /** @return array<string,int> */
    public static function limits(): array
    {
        return [
            'references_per_graph' => IngestGraph::MAX_REFERENCES,
            'resources_per_graph' => IngestGraph::MAX_RESOURCES,
            'functions_per_reference' => IngestGraph::MAX_FUNCTIONS,
            'edges_per_graph' => IngestGraph::MAX_EDGES,
            'text_length' => 2000,
        ];
    }

    /** @return array<string,mixed> */
    public static function example(): array
    {
        return [
            'title' => 'Link issues to milestones',
            'summary' => 'Adds issues.project_milestone_id and lets the MCP set it by milestone name or id.',
            'stage' => 'implemented',
            'repo' => 'open-aims',
            'ref' => 'feat/issue-milestones',
            'references' => [
                [
                    'id' => 'update',
                    'file' => 'app/Modules/Issues/Mcp/Tools/IssuesUpdate.php',
                    'context' => 'backend',
                    'role' => 'mcp_tool',
                    'change' => 'modified',
                    'namespace' => 'App\\Modules\\Issues\\Mcp\\Tools',
                    'class' => 'IssuesUpdate',
                    'class_type' => 'class',
                    'extends' => 'Tool',
                    'description' => 'MCP tool for partial updates of an issue.',
                    'purpose' => 'Entry point for MCP clients to change issue fields.',
                    'summary' => 'Accepts milestone and resolves it inside the issue project.',
                    'functions' => [[
                        'name' => 'handle',
                        'signature' => 'handle(Request $request): Response',
                        'visibility' => 'public',
                        'summary' => 'Validates the input, resolves the milestone and saves the issue.',
                        'side_effects' => ['db_read', 'db_write'],
                    ]],
                ],
                [
                    'id' => 'resolver',
                    'file' => 'app/Modules/Issues/Mcp/Tools/ResolvesIssueRefs.php',
                    'context' => 'backend',
                    'role' => 'util',
                    'change' => 'modified',
                    'namespace' => 'App\\Modules\\Issues\\Mcp\\Tools',
                    'class' => 'ResolvesIssueRefs',
                    'class_type' => 'trait',
                    'description' => 'Shared lookups for the Issues MCP tools.',
                    'purpose' => 'Keeps identifier, label and milestone resolution consistent across tools.',
                    'summary' => 'Adds resolveProjectMilestone.',
                    'functions' => [[
                        'name' => 'resolveProjectMilestone',
                        'signature' => 'resolveProjectMilestone(?int $projectId, string $reference): array',
                        'visibility' => 'private',
                        'change' => 'added',
                        'summary' => 'Finds a milestone by id or case-insensitive name within one project.',
                        'side_effects' => ['db_read'],
                    ]],
                ],
                [
                    'id' => 'tests',
                    'file' => 'tests/Feature/Mcp/Issues/IssueMilestonesTest.php',
                    'context' => 'tests',
                    'role' => 'test',
                    'change' => 'added',
                    'description' => 'Feature tests for milestone links over MCP.',
                    'purpose' => 'Pins linking, validation and progress behaviour.',
                    'summary' => 'Covers name, id, wrong project and progress cases.',
                    'functions' => [],
                ],
            ],
            'resources' => [[
                'id' => 'column',
                'type' => 'column',
                'name' => 'issues.project_milestone_id',
                'description' => 'Milestone an issue belongs to, inside its project.',
                'change' => 'added',
            ]],
            'edges' => [
                ['from' => 'update#handle', 'to' => 'resolver#resolveProjectMilestone', 'relation' => 'calls'],
                ['from' => 'update#handle', 'to' => 'column', 'relation' => 'writes'],
                ['from' => 'tests', 'to' => 'update', 'relation' => 'tests'],
            ],
        ];
    }
}
