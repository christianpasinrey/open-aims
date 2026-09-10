<?php

declare(strict_types=1);

namespace App\Modules\Graphs\Mcp\Tools;

use App\Modules\Graphs\Support\GraphSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description(
    'Explains the graph schema. Call it before building a graph: required and optional fields per level '
    .'(graph, reference, function, resource, edge), the canonical key format, every vocabulary value with its '
    .'meaning (stages, contexts, roles, changes, class types, visibilities, side effects, resource types, '
    .'relations with direction), limits, and a complete payload that graphs-attach accepts as is. '
    .'Pass topic to get only one part: graph, reference, function, resource, edge, keys, vocabulary, relations, example.'
)]
class GraphsSchema extends Tool
{
    public function handle(Request $request): Response
    {
        $data = Validator::make($request->all(), [
            'topic' => ['nullable', 'string', Rule::in(GraphSchema::TOPICS)],
        ])->validate();

        return Response::json(GraphSchema::topic($data['topic'] ?? 'all'));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topic' => $schema->string()->description(
                'all (default) | graph | reference | function | resource | edge | keys | vocabulary | relations | example'
            ),
        ];
    }
}
