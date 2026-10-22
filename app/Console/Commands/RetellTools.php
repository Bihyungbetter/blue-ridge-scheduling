<?php

namespace App\Console\Commands;

use App\Retell\ToolDefinitions;
use Illuminate\Console\Command;

class RetellTools extends Command
{
    protected $signature = 'retell:tools {--url= : Public base URL Retell can reach (defaults to APP_URL)}';

    protected $description = 'Print the general_tools JSON to paste into the Retell LLM config';

    public function handle(): int
    {
        $base = rtrim($this->option('url') ?: config('app.url'), '/');

        if (! str_starts_with($base, 'https://')) {
            $this->components->warn('Retell needs a public HTTPS URL. Use --url=https://<your tunnel or domain>.');
        }

        $this->line(json_encode(
            ['general_tools' => ToolDefinitions::all("{$base}/retell/functions")],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ));

        return self::SUCCESS;
    }
}
