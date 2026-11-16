<?php

declare(strict_types=1);

namespace App\Extraction\Text;

use App\Exceptions\TextExtractionFailedException;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Throwable;

final class CommandRunner
{
    /**
     * Runs a tool and returns its standard output.
     * Timeouts are re-thrown untouched, so the queue retries them.
     *
     * @param  list<string>  $command
     */
    public function run(string $tool, array $command): string
    {
        try {
            $result = Process::timeout((int) config('documind.extraction.tool_timeout'))->run($command);
        } catch (ProcessTimedOutException $e) {
            throw $e;
        } catch (Throwable) {
            throw TextExtractionFailedException::toolMissing($tool);
        }

        if ($result->exitCode() === 127) {
            throw TextExtractionFailedException::toolMissing($tool);
        }

        if ($result->failed()) {
            throw TextExtractionFailedException::toolFailed($tool, $result->errorOutput());
        }

        return $result->output();
    }
}
