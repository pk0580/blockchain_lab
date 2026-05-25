<?php

declare(strict_types=1);

namespace App\Modules\Idempotency\Infrastructure\Http;

use App\Modules\Idempotency\Application\UseCase\LookupIdempotentResponse\LookupIdempotentResponseAction;
use App\Modules\Idempotency\Application\UseCase\RecordIdempotentResponse\RecordIdempotentResponseAction;
use App\Modules\Idempotency\Domain\Exception\IdempotencyConflictException;
use App\Modules\Idempotency\Domain\ValueObject\HttpMethod;
use App\Modules\Idempotency\Domain\ValueObject\HttpPath;
use App\Modules\Idempotency\Domain\ValueObject\IdempotencyKey;
use App\Modules\Idempotency\Domain\ValueObject\RequestHash;
use App\Modules\Idempotency\Domain\ValueObject\ResponseSnapshot;
use Closure;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Глобальный HTTP-middleware идемпотентности (GUIDE.md, Урок 12.1).
 *
 * Алгоритм:
 *  1. Если метод не POST или нет заголовка `Idempotency-Key` — pass-through.
 *  2. Невалидный ключ (длина / charset) → 400 `idempotency_key_invalid`.
 *  3. request_hash = sha256(method + "\n" + path + "\n" + body).
 *  4. Lookup по key:
 *     - есть и тот же hash → replay оригинального ответа
 *       + заголовок `X-Idempotent-Replay: true` (клиент отличит cached);
 *     - есть и другой hash → 409 `idempotency_conflict`
 *       (тот же ключ, другой запрос — ошибка клиента);
 *     - нет → выполнить запрос.
 *  5. После выполнения: сохранить 2xx-4xx ответ с TTL 24 часа.
 *
 * ⚠️ 5xx НЕ кешируем (GUIDE §12.1, конец): транзиентные ошибки. Если закешировать,
 * клиент получит «вечный 500» на ретрай — это хуже, чем без кеша.
 *
 * ⚠️ Replay возвращает ОРИГИНАЛЬНЫЙ status (201/422/...), а не 200.
 *
 * @see \GUIDE.md  Урок 12 (#урок-12--надёжность-и-наблюдаемость)
 */
final readonly class IdempotencyMiddleware
{
    public const string HEADER = 'Idempotency-Key';
    public const string REPLAY_HEADER = 'X-Idempotent-Replay';

    public function __construct(
        private LookupIdempotentResponseAction $lookup,
        private RecordIdempotentResponseAction $record,
        private ConfigRepository $config,
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! $request->isMethod('POST')) {
            return $next($request);
        }

        $rawKey = $request->headers->get(self::HEADER);
        if ($rawKey === null || trim($rawKey) === '') {
            return $next($request);
        }

        try {
            $key = new IdempotencyKey($rawKey);
        } catch (InvalidArgumentException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'idempotency_key_invalid',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        $method = new HttpMethod(strtoupper($request->getMethod()));
        $path = new HttpPath('/'.ltrim($request->getPathInfo(), '/'));
        $hash = RequestHash::ofRequest(
            method: $method->value,
            path: $path->value,
            body: $request->getContent(),
        );

        try {
            $cached = $this->lookup->handle($key, $hash);
        } catch (IdempotencyConflictException $e) {
            return new JsonResponse([
                'error' => [
                    'code' => 'idempotency_conflict',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        }

        if ($cached !== null) {
            return new Response(
                $cached->response->body,
                $cached->response->status,
                [
                    'Content-Type' => 'application/json',
                    self::REPLAY_HEADER => 'true',
                ],
            );
        }

        $response = $next($request);

        if ($response instanceof SymfonyResponse
            && ResponseSnapshot::isStorableStatus($response->getStatusCode())
        ) {
            /** @var int $ttl */
            $ttl = $this->config->get('idempotency.ttl_seconds', 86400);

            $this->record->handle(
                key: $key,
                hash: $hash,
                method: $method,
                path: $path,
                response: new ResponseSnapshot(
                    status: $response->getStatusCode(),
                    body: (string) $response->getContent(),
                ),
                ttlSeconds: $ttl,
            );
        }

        return $response;
    }
}
