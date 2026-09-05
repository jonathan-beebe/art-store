<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Requests\Shop\ShopRequest;
use App\Support\DataRedaction;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * The request body as the opening `http.request` line carries it
 * (docs/spec.md §2.2 `data.body`): every field a form or a JSON client
 * sent, through the §2.1 redaction pass, with the card fields dropped by
 * name — a CVC or an expiry is too short for the pattern to catch — the
 * framework's own `_token` and `_method` left out, each upload reduced
 * to its name and size, and every value capped so a pasted essay stays
 * a log line. `/mcp` is skipped: `mcp.call` already carries the JSON-RPC
 * envelope's arguments.
 */
final class LoggedRequestBody
{
    public const int VALUE_CAP = 500;

    public const string CAP_MARK = '…';

    private const array FRAMEWORK_FIELDS = ['_token', '_method'];

    private const string MCP_PATH = '/mcp';

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<array-key, mixed>|null null when there is nothing to carry
     */
    public static function of(Request $request): ?array
    {
        if (in_array($request->method(), ['GET', 'HEAD'], true) || $request->getPathInfo() === self::MCP_PATH) {
            return null;
        }

        $fields = array_diff_key(
            [...($request->isJson() ? $request->json()->all() : $request->post()), ...$request->allFiles()],
            array_flip([...self::FRAMEWORK_FIELDS, ...ShopRequest::CARD_FIELDS]),
        );

        if ($fields === []) {
            return null;
        }

        return DataRedaction::redact(self::shaped($fields));
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<array-key, mixed>
     */
    private static function shaped(array $fields): array
    {
        return array_map(self::value(...), $fields);
    }

    private static function value(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['file' => $value->getClientOriginalName(), 'bytes' => $value->getSize()];
        }

        if (is_array($value)) {
            return self::shaped($value);
        }

        if (is_string($value) && mb_strlen($value) > self::VALUE_CAP) {
            return mb_substr($value, 0, self::VALUE_CAP).self::CAP_MARK;
        }

        return $value;
    }
}
