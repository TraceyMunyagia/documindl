<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

class DoctorCommand extends Command
{
    protected $signature = 'documind:doctor';

    protected $description = 'Check that the tools DocuMind needs for text extraction are installed';

    public function handle(): int
    {
        $healthy = true;

        $this->components->info('Extraction tools');

        $tools = [
            'pdftotext' => [config('documind.extraction.pdftotext'), '-v'],
            'pdftoppm' => [config('documind.extraction.pdftoppm'), '-v'],
            'tesseract' => [config('documind.extraction.tesseract'), '--version'],
        ];

        foreach ($tools as $name => $command) {
            $version = $this->firstLineOf($command);

            if ($version === null) {
                $healthy = false;
                $this->components->twoColumnDetail($name, '<fg=red>not found</> ('.$command[0].')');
            } else {
                $this->components->twoColumnDetail($name, "<fg=green>{$version}</>");
            }
        }

        $this->components->info('OCR languages');

        $installed = $this->installedLanguages();

        foreach (explode('+', config('documind.extraction.ocr_languages')) as $language) {
            $found = in_array($language, $installed, true);
            $healthy = $healthy && $found;
            $this->components->twoColumnDetail($language, $found ? '<fg=green>installed</>' : '<fg=red>missing</>');
        }

        $this->components->info('Queue');
        $this->components->twoColumnDetail('Connection', config('queue.default'));
        $this->components->twoColumnDetail('retry_after (seconds)', (string) config('queue.connections.database.retry_after'));

        $healthy
            ? $this->components->success('DocuMind can read PDFs and images.')
            : $this->components->error('Some tools are missing. Install them with: brew install poppler tesseract');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    /** @param list<string> $command */
    private function firstLineOf(array $command): ?string
    {
        try {
            $result = Process::run($command);
        } catch (Throwable) {
            return null;
        }

        if ($result->failed()) {
            return null;
        }

        // Poppler prints its version to stderr, Tesseract to stdout.
        return Str::of($result->output()."\n".$result->errorOutput())
            ->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->first(fn (string $line): bool => $line !== '');
    }

    /** @return list<string> */
    private function installedLanguages(): array
    {
        try {
            $result = Process::run([config('documind.extraction.tesseract'), '--list-langs']);
        } catch (Throwable) {
            return [];
        }

        return Str::of($result->output()."\n".$result->errorOutput())
            ->explode("\n")
            ->map(fn (string $line): string => trim($line))
            ->reject(fn (string $line): bool => $line === '' || str_starts_with($line, 'List of'))
            ->values()
            ->all();
    }
}
