<?php

declare(strict_types=1);

namespace App\Modules\Education\Infrastructure\Provider;

use App\Modules\Education\Application\Contract\Dashboard\LedgerOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\MempoolOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\NodeHealthOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\OutboxOverviewProvider;
use App\Modules\Education\Application\Contract\Dashboard\WithdrawalQueueOverviewProvider;
use App\Modules\Education\Application\Contract\PlaygroundClient;
use App\Modules\Education\Application\Contract\RegtestRpcClient;
use App\Modules\Education\Domain\Repository\LessonRepository;
use App\Modules\Education\Infrastructure\Dashboard\Provider\EloquentLedgerOverviewProvider;
use App\Modules\Education\Infrastructure\Dashboard\Provider\EloquentOutboxOverviewProvider;
use App\Modules\Education\Infrastructure\Dashboard\Provider\EloquentWithdrawalQueueOverviewProvider;
use App\Modules\Education\Infrastructure\Dashboard\Provider\RegistryNodeHealthOverviewProvider;
use App\Modules\Education\Infrastructure\Dashboard\Provider\RegtestMempoolOverviewProvider;
use App\Modules\Education\Infrastructure\Http\HttpPlaygroundClient;
use App\Modules\Education\Infrastructure\Http\HttpRegtestRpcClient;
use App\Modules\Education\Infrastructure\Persistence\Filesystem\MarkdownLessonRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use League\CommonMark\CommonMarkConverter;

final class EducationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LessonRepository::class, function ($app): LessonRepository {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            /** @var string $root */
            $root = $config->get('education.content_path', base_path('content/lessons'));

            return new MarkdownLessonRepository(
                contentRoot: $root,
                converter: new CommonMarkConverter([
                    'html_input' => 'allow',
                    'allow_unsafe_links' => false,
                ]),
            );
        });

        $this->app->singleton(PlaygroundClient::class, function ($app): PlaygroundClient {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            /** @var string $baseUrl */
            $baseUrl = $config->get('network.signing.url', 'http://signing-svc:8080');
            /** @var string $token */
            $token = (string) $config->get('network.signing.bearer_token', '');
            /** @var int $timeout */
            $timeout = (int) $config->get('education.playground.timeout_seconds', 10);

            return new HttpPlaygroundClient(
                http: $app->make(HttpFactory::class),
                baseUrl: $baseUrl,
                bearerToken: $token,
                timeoutSeconds: $timeout,
            );
        });

        $this->app->singleton(RegtestRpcClient::class, function ($app): RegtestRpcClient {
            /** @var \Illuminate\Contracts\Config\Repository $config */
            $config = $app->make('config');
            /** @var string $url */
            $url = (string) $config->get('education.regtest.url', 'http://bitcoin-regtest:18443');
            /** @var string $user */
            $user = (string) $config->get('education.regtest.user', 'bitcoin');
            /** @var string $password */
            $password = (string) $config->get('education.regtest.password', 'bitcoin');

            return new HttpRegtestRpcClient(
                http: $app->make(HttpFactory::class),
                url: $url,
                user: $user,
                password: $password,
            );
        });

        // Phase 8.4 — 5 read-провайдеров для admin dashboard'а. Singleton ок:
        // ни один из них не держит state'а между запросами.
        $this->app->singleton(NodeHealthOverviewProvider::class, RegistryNodeHealthOverviewProvider::class);
        $this->app->singleton(MempoolOverviewProvider::class, RegtestMempoolOverviewProvider::class);
        $this->app->singleton(LedgerOverviewProvider::class, EloquentLedgerOverviewProvider::class);
        $this->app->singleton(WithdrawalQueueOverviewProvider::class, EloquentWithdrawalQueueOverviewProvider::class);
        $this->app->singleton(OutboxOverviewProvider::class, EloquentOutboxOverviewProvider::class);
    }
}
