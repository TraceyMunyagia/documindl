<?php

namespace Tests;

use App\Jobs\ProcessDocument;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Uploads queue ProcessDocument; tests that want it to run call dispatchSync().
        Queue::fake([ProcessDocument::class]);

        // Never run real binaries unless a test fakes them or explicitly opts in.
        Process::preventStrayProcesses();
    }
}
