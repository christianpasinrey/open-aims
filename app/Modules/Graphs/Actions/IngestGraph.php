<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Actions;

use App\Modules\Graphs\Exceptions\InvalidGraphPayload;
use App\Modules\Graphs\Models\Graph;
use App\Modules\Graphs\Models\GraphEdge;
use App\Modules\Graphs\Models\GraphNode;
use App\Modules\Graphs\Models\GraphReference;
use App\Modules\Graphs\Support\GraphVocabulary;
use App\Modules\Graphs\Support\NodeKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

/**
 * Stores a graph of code references for an owner (issue, project, milestone).
 *
 * Everything is validated before anything is written. Sending a graph again
 * with the same title for the same owner replaces its references and edges
 * and bumps its version. Nodes are canonical per workspace, so graphs that
 * mention the same file, class, function or resource share a node.
 */
final readonly class IngestGraph
{
    public const MAX_REFERENCES = 300;

    public const MAX_RESOURCES = 300;

    public const MAX_FUNCTIONS = 100;

    public const MAX_EDGES = 2000;

    private const MAX_TEXT = 2000;

    private const LOCAL_ID = '/^[A-Za-z0-9_.-]+$/';

    /** When two references point at the same node, the strongest change wins. */
    private const CHANGE_WEIGHT = ['read' => 0, 'modified' => 1, 'added' => 2, 'removed' => 3];

    /**
     * @param  array<string,mixed>  $payload
     */
    public function __invoke(Model $owner, array $payload, ?int $userId): Graph
    {
        $workspaceId = $this->workspaceIdOf($owner);

        $this->validateSchema($payload);
        $endpoints = $this->localEndpoints($payload);
        $this->validateEdges($payload, $endpoints, $workspaceId);

        return DB::transaction(fn (): Graph => $this->write($owner, $workspaceId, $payload, $endpoints, $userId));
    }

    /**
     * Issues and projects carry workspace_id; milestones reach it through
     * their project.
     */
    private function workspaceIdOf(Model $owner): int
    {
        $workspaceId = $owner->getAttribute('workspace_id');

        if ($workspaceId === null && method_exists($owner, 'project')) {
            $workspaceId = $owner->project()->withoutGlobalScopes()->value('workspace_id');
        }

        if ($workspaceId === null) {
            throw new InvalidArgumentException('A graph owner must belong to a workspace.');
        }

        return (int) $workspaceId;
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $payload
     */
    private function validateSchema(array $payload): void
    {
        $text = ['required', 'string', 'max:'.self::MAX_TEXT];

        $validator = Validator::make($payload, [
            'title' => ['required', 'string', 'max:200'],
            'summary' => $text,
            'stage' => ['required', Rule::in(GraphVocabulary::STAGES)],
            'repo' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9._-]*$/'],
            'ref' => ['required', 'string', 'max:191'],

            'references' => ['required', 'array', 'min:1', 'max:'.self::MAX_REFERENCES],
            'references.*' => ['array'],
            'references.*.id' => ['required', 'string', 'max:100', 'regex:'.self::LOCAL_ID, 'distinct'],
            'references.*.file' => ['required', 'string', 'max:512', $this->safePath()],
            'references.*.context' => ['required', Rule::in(GraphVocabulary::CONTEXTS)],
            'references.*.role' => ['required', Rule::in(GraphVocabulary::ROLES)],
            'references.*.change' => ['required', Rule::in(GraphVocabulary::CHANGES)],
            'references.*.description' => $text,
            'references.*.purpose' => $text,
            'references.*.summary' => $text,
            'references.*.language' => ['nullable', 'string', 'max:32'],
            'references.*.class' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z_$][A-Za-z0-9_$]*$/'],
            'references.*.namespace' => ['required_with:references.*.class', 'nullable', 'string', 'max:255'],
            'references.*.class_type' => ['required_with:references.*.class', 'nullable', Rule::in(GraphVocabulary::CLASS_TYPES)],
            'references.*.extends' => ['nullable', 'string', 'max:255'],
            'references.*.implements' => ['nullable', 'array'],
            'references.*.implements.*' => ['string', 'max:255'],

            'references.*.functions' => ['present', 'array', 'max:'.self::MAX_FUNCTIONS],
            'references.*.functions.*' => ['array'],
            'references.*.functions.*.name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z_$][A-Za-z0-9_$]*$/'],
            'references.*.functions.*.signature' => ['required', 'string', 'max:1024'],
            'references.*.functions.*.visibility' => ['required', Rule::in(GraphVocabulary::VISIBILITIES)],
            'references.*.functions.*.summary' => $text,
            'references.*.functions.*.change' => ['nullable', Rule::in(GraphVocabulary::CHANGES)],
            'references.*.functions.*.lines' => ['nullable', 'array', 'size:2'],
            'references.*.functions.*.lines.*' => ['integer', 'min:1'],
            'references.*.functions.*.side_effects' => ['nullable', 'array'],
            'references.*.functions.*.side_effects.*' => [Rule::in(GraphVocabulary::SIDE_EFFECTS)],
            'references.*.functions.*.throws' => ['nullable', 'array'],
            'references.*.functions.*.throws.*' => ['string', 'max:255'],

            'resources' => ['nullable', 'array', 'max:'.self::MAX_RESOURCES],
            'resources.*' => ['array'],
            'resources.*.id' => ['required', 'string', 'max:100', 'regex:'.self::LOCAL_ID, 'distinct'],
            'resources.*.type' => ['required', Rule::in(GraphVocabulary::RESOURCE_TYPES)],
            'resources.*.name' => ['required', 'string', 'max:255'],
            'resources.*.description' => $text,
            'resources.*.change' => ['required', Rule::in(GraphVocabulary::CHANGES)],

            'edges' => ['nullable', 'array', 'max:'.self::MAX_EDGES],
            'edges.*' => ['array'],
            'edges.*.from' => ['required', 'string', 'max:1100'],
            'edges.*.to' => ['required', 'string', 'max:1100'],
            'edges.*.relation' => ['required', Rule::in(GraphVocabulary::RELATIONS)],
            'edges.*.label' => ['nullable', 'string', 'max:255'],
        ]);

        $problems = [];
        foreach ($validator->errors()->messages() as $key => $messages) {
            $problems[] = $this->readablePath((string) $key, $payload).': '.$messages[0];
        }

        $referenceIds = array_column($payload['references'] ?? [], 'id');
        foreach ($payload['resources'] ?? [] as $resource) {
            if (is_array($resource) && in_array($resource['id'] ?? null, $referenceIds, true)) {
                $problems[] = "resources[{$resource['id']}].id: already used by a reference.";
            }
        }

        if ($problems !== []) {
            throw InvalidGraphPayload::because(array_slice($problems, 0, 25));
        }
    }

    private function safePath(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $segments = explode('/', $value);
            $unsafe = str_starts_with($value, '/')
                || str_contains($value, '\\')
                || str_contains($value, '#')
                || str_contains($value, ':')
                || in_array('..', $segments, true)
                || in_array('', $segments, true);

            if ($unsafe) {
                $fail('must be a path relative to the repository root using "/", without "..", "#" or ":".');
            }
        };
    }

    /** `references.0.functions.1.name` → `references[update].functions.1.name`. */
    private function readablePath(string $key, array $payload): string
    {
        return (string) preg_replace_callback(
            '/^(references|resources)\.(\d+)/',
            static function (array $m) use ($payload): string {
                $id = $payload[$m[1]][(int) $m[2]]['id'] ?? null;

                return is_string($id) && $id !== '' ? "{$m[1]}[{$id}]" : $m[0];
            },
            $key,
        );
    }

    /**
     * Every way an edge can point inside this payload, mapped to its canonical key.
     *
     * @param  array<string,mixed>  $payload
     * @return array<string,string>
     */
    private function localEndpoints(array $payload): array
    {
        $repo = $payload['repo'];
        $endpoints = [];

        foreach ($payload['references'] as $reference) {
            $class = $reference['class'] ?? null;
            $endpoints[$reference['id']] = $class !== null
                ? NodeKey::forClass($repo, $reference['file'], $class)
                : NodeKey::forFile($repo, $reference['file']);

            foreach ($reference['functions'] as $function) {
                $endpoints[$reference['id'].'#'.$function['name']] =
                    NodeKey::forFunction($repo, $reference['file'], $class, $function['name']);
            }
        }

        foreach ($payload['resources'] ?? [] as $resource) {
            $endpoints[$resource['id']] = NodeKey::forResource($repo, $resource['type'], $resource['name']);
        }

        return $endpoints;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $endpoints
     */
    private function validateEdges(array $payload, array $endpoints, int $workspaceId): void
    {
        $payloadKeys = array_flip($endpoints);
        $problems = [];

        foreach ($payload['edges'] ?? [] as $index => $edge) {
            foreach (['from', 'to'] as $side) {
                $endpoint = $edge[$side];

                if (isset($endpoints[$endpoint])) {
                    continue;
                }

                if (NodeKey::isCanonical($endpoint)
                    && (isset($payloadKeys[$endpoint]) || $this->existingNodeId($workspaceId, $endpoint) !== null)) {
                    continue;
                }

                $problems[] = "edges.{$index}.{$side}: '{$endpoint}' is not a reference, reference#function "
                    .'or resource of this graph, nor an existing node key.';
            }
        }

        if ($problems !== []) {
            throw InvalidGraphPayload::because(array_slice($problems, 0, 25));
        }
    }

    private function existingNodeId(int $workspaceId, string $key): ?int
    {
        $id = GraphNode::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('key_hash', NodeKey::hash($key))
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    /**
     * @param  array<string,mixed>  $payload
     * @param  array<string,string>  $endpoints
     */
    private function write(Model $owner, int $workspaceId, array $payload, array $endpoints, ?int $userId): Graph
    {
        $graph = $this->graphFor($owner, $workspaceId, $payload, $userId);
        $repo = $payload['repo'];

        /** @var array<string,int> $nodeIds canonical key → node id */
        $nodeIds = [];
        /** @var array<int,array<string,mixed>> $references node id → reference row */
        $references = [];

        foreach ($payload['references'] as $reference) {
            $file = $reference['file'];
            $class = $reference['class'] ?? null;
            $language = $reference['language'] ?? GraphVocabulary::languageOf($file);
            $texts = [
                'description' => $reference['description'],
                'purpose' => $reference['purpose'],
                'summary' => $reference['summary'],
            ];
            $shared = [
                'repo' => $repo,
                'file_path' => $file,
                'language' => $language,
                'context' => $reference['context'],
                'role' => $reference['role'],
            ];

            $fileNode = $this->upsertNode($workspaceId, NodeKey::forFile($repo, $file), $nodeIds, [
                'kind' => 'file',
            ] + $shared + $texts);
            $this->addReference($references, $fileNode, $reference['change'], $shared + $texts, null);
            $anchor = $fileNode;

            if ($class !== null) {
                $classMeta = array_filter([
                    'extends' => $reference['extends'] ?? null,
                    'implements' => $reference['implements'] ?? null,
                ]);
                $anchor = $this->upsertNode($workspaceId, NodeKey::forClass($repo, $file, $class), $nodeIds, [
                    'kind' => 'class',
                    'parent_node_id' => $fileNode->id,
                    'namespace' => $reference['namespace'],
                    'class_name' => $class,
                    'class_type' => $reference['class_type'],
                    'meta' => $classMeta ?: null,
                ] + $shared + $texts);
                $this->addReference($references, $anchor, $reference['change'], $shared + $texts, $classMeta ?: null);
            }

            foreach ($reference['functions'] as $function) {
                $functionMeta = array_filter([
                    'signature' => $function['signature'],
                    'visibility' => $function['visibility'],
                    'lines' => $function['lines'] ?? null,
                    'side_effects' => $function['side_effects'] ?? null,
                    'throws' => $function['throws'] ?? null,
                ]);
                $node = $this->upsertNode(
                    $workspaceId,
                    NodeKey::forFunction($repo, $file, $class, $function['name']),
                    $nodeIds,
                    [
                        'kind' => 'function',
                        'parent_node_id' => $anchor->id,
                        'namespace' => $class !== null ? $reference['namespace'] : null,
                        'class_name' => $class,
                        'function_name' => $function['name'],
                        'signature' => $function['signature'],
                        'visibility' => $function['visibility'],
                        'summary' => $function['summary'],
                        'meta' => array_diff_key($functionMeta, ['signature' => 1, 'visibility' => 1]) ?: null,
                    ] + $shared,
                );
                $this->addReference(
                    $references,
                    $node,
                    $function['change'] ?? $reference['change'],
                    $shared + ['description' => null, 'purpose' => null, 'summary' => $function['summary']],
                    $functionMeta,
                );
            }
        }

        foreach ($payload['resources'] ?? [] as $resource) {
            $node = $this->upsertNode(
                $workspaceId,
                NodeKey::forResource($repo, $resource['type'], $resource['name']),
                $nodeIds,
                [
                    'kind' => 'resource',
                    'repo' => $repo,
                    'resource_type' => $resource['type'],
                    'resource_name' => $resource['name'],
                    'description' => $resource['description'],
                ],
            );
            $this->addReference($references, $node, $resource['change'], [
                'context' => null,
                'role' => null,
                'description' => $resource['description'],
                'purpose' => null,
                'summary' => null,
            ], null);
        }

        $this->insertReferences($graph, $references);
        $this->insertEdges($graph, $payload['edges'] ?? [], $endpoints, $nodeIds, $workspaceId);

        return $graph->fresh();
    }

    /**
     * @param  array<string,mixed>  $payload
     */
    private function graphFor(Model $owner, int $workspaceId, array $payload, ?int $userId): Graph
    {
        $attributes = [
            'summary' => $payload['summary'],
            'stage' => $payload['stage'],
            'repo' => $payload['repo'],
            'ref' => $payload['ref'],
        ];

        $graph = Graph::query()
            ->withoutGlobalScopes()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->where('title', $payload['title'])
            ->lockForUpdate()
            ->first();

        if ($graph === null) {
            return Graph::create([
                'workspace_id' => $workspaceId,
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'title' => $payload['title'],
                'version' => 1,
                'created_by_user_id' => $userId,
            ] + $attributes);
        }

        $graph->references()->delete();
        $graph->edges()->delete();
        $graph->fill($attributes + ['version' => $graph->version + 1])->save();

        return $graph;
    }

    /**
     * Latest non-null values win on the canonical node; per-graph values
     * live on the reference rows.
     *
     * @param  array<string,int>  $nodeIds
     * @param  array<string,mixed>  $attributes
     */
    private function upsertNode(int $workspaceId, string $key, array &$nodeIds, array $attributes): GraphNode
    {
        $hash = NodeKey::hash($key);
        $attributes = array_filter($attributes, static fn ($value): bool => $value !== null);
        $attributes['last_referenced_at'] = now();

        $node = GraphNode::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('key_hash', $hash)
            ->first();

        if ($node === null) {
            $node = GraphNode::create([
                'workspace_id' => $workspaceId,
                'key' => $key,
                'key_hash' => $hash,
            ] + $attributes);
        } else {
            $node->fill($attributes)->save();
        }

        $nodeIds[$key] = (int) $node->id;

        return $node;
    }

    /**
     * @param  array<int,array<string,mixed>>  $references
     * @param  array<string,mixed>  $fields
     * @param  array<string,mixed>|null  $meta
     */
    private function addReference(array &$references, GraphNode $node, string $change, array $fields, ?array $meta): void
    {
        $existing = $references[$node->id] ?? null;

        if ($existing !== null) {
            if (self::CHANGE_WEIGHT[$change] > self::CHANGE_WEIGHT[$existing['change']]) {
                $references[$node->id]['change'] = $change;
            }

            return;
        }

        $references[$node->id] = [
            'graph_node_id' => $node->id,
            'context' => $fields['context'] ?? null,
            'role' => $fields['role'] ?? null,
            'change' => $change,
            'description' => $fields['description'] ?? null,
            'purpose' => $fields['purpose'] ?? null,
            'summary' => $fields['summary'] ?? null,
            'meta' => $meta,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $references
     */
    private function insertReferences(Graph $graph, array $references): void
    {
        $now = now();
        $rows = array_map(static fn (array $row): array => [
            'graph_id' => $graph->id,
            'meta' => $row['meta'] !== null ? json_encode($row['meta']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ] + $row, array_values($references));

        foreach (array_chunk($rows, 500) as $chunk) {
            GraphReference::query()->insert($chunk);
        }
    }

    /**
     * @param  list<array<string,mixed>>  $edges
     * @param  array<string,string>  $endpoints
     * @param  array<string,int>  $nodeIds
     */
    private function insertEdges(Graph $graph, array $edges, array $endpoints, array $nodeIds, int $workspaceId): void
    {
        $now = now();
        $rows = [];

        foreach ($edges as $edge) {
            $source = $this->resolveEndpoint($edge['from'], $endpoints, $nodeIds, $workspaceId);
            $target = $this->resolveEndpoint($edge['to'], $endpoints, $nodeIds, $workspaceId);
            $unique = $source.'|'.$target.'|'.$edge['relation'];

            $rows[$unique] ??= [
                'graph_id' => $graph->id,
                'source_node_id' => $source,
                'target_node_id' => $target,
                'relation' => $edge['relation'],
                'label' => $edge['label'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            GraphEdge::query()->insert($chunk);
        }
    }

    /**
     * @param  array<string,string>  $endpoints
     * @param  array<string,int>  $nodeIds
     */
    private function resolveEndpoint(string $endpoint, array $endpoints, array $nodeIds, int $workspaceId): int
    {
        $key = $endpoints[$endpoint] ?? $endpoint;

        return $nodeIds[$key] ?? (int) $this->existingNodeId($workspaceId, $key);
    }
}
