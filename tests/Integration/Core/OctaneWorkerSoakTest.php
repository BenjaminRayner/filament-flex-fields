<?php

declare(strict_types=1);

namespace Bjanczak\FilamentFlexFields\Tests\Integration\Core;

use Bjanczak\FilamentFlexFields\Tests\Support\Core\ExpectedScopeFailure;
use Bjanczak\FilamentFlexFields\Tests\Support\Core\ScopeScenario;
use Bjanczak\FilamentFlexFields\Tests\Support\Core\WorkerReport;
use Bjanczak\FilamentFlexFields\Tests\Support\Core\WorkerServiceProvider;
use Bjanczak\FilamentFlexFields\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\OctaneServiceProvider;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;
use RuntimeException;
use Throwable;

/** Explicit optional suite: uses Octane's worker, kernel and production reset listeners. */
final class OctaneWorkerSoakTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        if (! class_exists(OctaneServiceProvider::class)) {
            throw new RuntimeException('The optional core suite requires laravel/octane ^2.');
        }

        return [...parent::getPackageProviders($app), OctaneServiceProvider::class, WorkerServiceProvider::class];
    }

    public function test_octane_sandbox_releases_tenant_state_after_success_and_failure(): void
    {
        $iterations = WorkerReport::iterations();
        $report = $this->app->make(WorkerReport::class);
        $this->app->make(ExceptionHandler::class)->renderable(static function (ExpectedScopeFailure $exception) use ($report) {
            $report->expectedFailures++;

            return response()->json(['failure' => 'expected'], 503);
        });
        $this->app->make(ExceptionHandler::class)->renderable(static function (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 500);
        });
        $this->app['router']->get('/core-probe/{iteration}', static function (string $iteration) {
            ScopeScenario::run((int) $iteration);

            return response()->json(['ok' => true]);
        });
        $factory = new class($this->app) extends ApplicationFactory
        {
            public function __construct(private readonly Application $fixture) {}

            public function createApplication(array $initialInstances = []): Application
            {
                foreach ($initialInstances as $key => $value) {
                    $this->fixture->instance($key, $value);
                }

                return $this->warm($this->fixture);
            }
        };
        $client = new class implements Client
        {
            public int $responses = 0;

            public int $failures = 0;

            /** @var list<string> */
            public array $errors = [];

            public function marshalRequest(RequestContext $context): array
            {
                throw new RuntimeException('This harness supplies requests directly to the real worker.');
            }

            public function respond(RequestContext $context, OctaneResponse $response): void
            {
                $this->responses++;
                if ($response->response->getStatusCode() === 503 && $response->response->getContent() === '{"failure":"expected"}') {
                    $this->failures++;
                } elseif ($response->response->getStatusCode() !== 200 || $response->response->getContent() !== '{"ok":true}') {
                    if (count($this->errors) < 3) {
                        $this->errors[] = 'HTTP '.$response->response->getStatusCode().' '.substr((string) $response->response->getContent(), 0, 300);
                    }
                }
            }

            public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
            {
                if (count($this->errors) < 3) {
                    $this->errors[] = $e->getMessage();
                }
            }
        };
        $worker = new Worker($factory, $client);
        $worker->boot();
        try {
            for ($iteration = 1; $iteration <= $iterations; $iteration++) {
                $worker->handle(Request::create('/core-probe/'.$iteration, 'GET'), new RequestContext);
                if ($client->errors !== []) {
                    break;
                }
            }
        } finally {
            $worker->terminate();
        }
        $this->assertSame([], $client->errors);
        $this->assertSame($iterations, $client->responses);
        $this->assertSame($iterations, $report->completed);
        $this->assertSame(intdiv($iterations, 97), $report->expectedFailures);
        $this->assertSame($report->expectedFailures, $client->failures);
        $this->assertSame($iterations * 2, $report->factoryCalls);
        $report->assertStable();
        fwrite(STDERR, PHP_EOL.$report->describe('octane-worker-http-kernel').PHP_EOL);
    }
}
