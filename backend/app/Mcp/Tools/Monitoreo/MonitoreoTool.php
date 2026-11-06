<?php

namespace App\Mcp\Tools\Monitoreo;

use App\Domain\Identity\Models\User;
use App\Domain\Shared\Exceptions\BusinessRuleViolation;
use App\Domain\Support\Models\Report;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

/**
 * A tool of monitoreo's MCP (httpapi/mcp.go): a thin adapter over the queries and actions the web
 * uses. Each tool declares its Go input properties and an `answer(...)` method the container calls
 * with its dependencies. Like Go's decodeArgs, unknown or mistyped arguments are refused, and a
 * refused rule comes back as a tool error with the same message as the web.
 */
abstract class MonitoreoTool extends Tool
{
    protected const ALL = ['type' => 'boolean',
        'description' => 'true trae los proyectos de todos. Por defecto solo los tuyos (principal o secundario) y los que no tienen desarrolladores.'];

    protected const ID = ['type' => 'integer', 'description' => 'Número del reporte (#12 → 12).'];

    protected const PROJECT = ['type' => 'string', 'description' => 'Proyecto: id, slug o parte del nombre (ej. "playinn").'];

    private const TYPE_NAMES = ['string' => 'un texto', 'integer' => 'un número entero', 'boolean' => 'true o false', 'array' => 'una lista de textos'];

    /** The input properties, as monitoreo publishes them. @return array<string, array<string, mixed>> */
    abstract protected function properties(): array;

    /** @return list<string> */
    protected function required(): array
    {
        return [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $problem = $this->argumentProblem($request->all());
        if ($problem !== null) {
            return Response::error("Argumentos inválidos: {$problem}.");
        }
        try {
            return Container::getInstance()->call([$this, 'answer'], ['request' => $request]);
        } catch (BusinessRuleViolation $e) {
            return Response::error($e->getMessage());
        }
    }

    /** monitoreo's tool JSON: name, description, its strict input schema and the annotations (NV-06). @return array<string, mixed> */
    public function toArray(): array
    {
        $schema = ['type' => 'object', 'properties' => $this->properties() ?: (object) [], 'additionalProperties' => false];
        if ($this->required() !== []) {
            $schema['required'] = $this->required();
        }
        $title = $this->resolveAttribute(Title::class)?->value;

        return [
            'name' => $this->name(),
            'description' => $this->description(),
            'inputSchema' => $schema,
            'annotations' => $title === null ? $this->annotations() : ['title' => $title, ...$this->annotations()],
        ];
    }

    /** The token's owner: the MCP route authenticated them. */
    protected static function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    /** The report a tool names by its number. */
    protected static function report(mixed $id): Report
    {
        $id = (int) $id;

        return ($id > 0 ? Report::query()->find($id) : null) ?? throw new BusinessRuleViolation("No existe el reporte #{$id}.", 'not_found', 404);
    }

    /**
     * Marks text a client wrote, or that came from its app, as data for the model (D-092, NV-06). The text
     * goes JSON-quoted with <, > and & escaped, so it can neither close the tag nor open a new one.
     */
    protected static function untrusted(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        $quoted = (string) json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return '<datos_del_cliente>'.preg_replace_callback('/[<>&]/', fn (array $char) => sprintf('\u%04x', ord($char[0])), $quoted).'</datos_del_cliente>';
    }

    /** The parts of an app's errors its visitors can shape. @param  list<array<string, mixed>>  $items @return list<array<string, mixed>> */
    protected static function untrustedErrors(array $items): array
    {
        return array_map(fn (array $item) => array_replace($item, [
            'message' => self::untrusted((string) ($item['message'] ?? '')),
            'url' => self::untrusted((string) ($item['url'] ?? '')),
        ]), $items);
    }

    /** Go's jsonResult: indented by two, < and > as they are so the tags read as tags. @param  array<string, mixed>  $value */
    protected static function json(array $value): Response
    {
        $json = (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        return Response::text((string) preg_replace_callback('/^(?: {4})+/m', fn (array $indent) => str_repeat('  ', intdiv(strlen($indent[0]), 4)), $json));
    }

    /** @param  array<string, mixed>  $arguments */
    private function argumentProblem(array $arguments): ?string
    {
        $properties = $this->properties();
        foreach ($arguments as $key => $value) {
            $type = $properties[$key]['type'] ?? null;
            if ($type === null) {
                return "campo desconocido \"{$key}\"";
            }
            $valid = $value === null || match ($type) {
                'string' => is_string($value),
                'integer' => is_int($value),
                'boolean' => is_bool($value),
                default => is_array($value) && array_is_list($value) && array_filter($value, fn (mixed $item) => ! is_string($item)) === [],
            };
            if (! $valid) {
                return "{$key} tiene que ser ".self::TYPE_NAMES[$type];
            }
        }

        return null;
    }
}
