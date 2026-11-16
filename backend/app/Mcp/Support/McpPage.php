<?php

namespace App\Mcp\Support;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Laravel\Mcp\Request;

/**
 * Pagination of the MCP list tools: `limit` (default 25, max 50) and `page`, answered with `total`,
 * `page` and `has_more` so the model knows when to ask for the next page instead of guessing.
 */
final class McpPage
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 50;

    /** @return array<string, string> */
    public static function rules(): array
    {
        return ['limit' => 'nullable|integer|between:1,'.self::MAX_LIMIT, 'page' => 'nullable|integer|min:1'];
    }

    /** @return array<string, mixed> */
    public static function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Cuántos traer por página (default '.self::DEFAULT_LIMIT.', máximo '.self::MAX_LIMIT.').'),
            'page' => $schema->integer()->description('Página (default 1). Pedí la siguiente solo si has_more es true.'),
        ];
    }

    public static function limit(Request $request): int
    {
        return (int) $request->get('limit', self::DEFAULT_LIMIT);
    }

    public static function page(Request $request): int
    {
        return (int) $request->get('page', 1);
    }

    /**
     * @template TItem
     *
     * @param  LengthAwarePaginator<int, TItem>  $paginator
     * @param  Closure(TItem): array<string, mixed>  $present
     * @return array<string, mixed>
     */
    public static function of(LengthAwarePaginator $paginator, string $key, Closure $present): array
    {
        return [
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
            $key => array_map($present, $paginator->items()),
        ];
    }
}
