<?php

namespace App\Console\Commands;

use App\Retell\AgentContext;
use App\Retell\RetellClient;
use App\Retell\ToolDefinitions;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

/**
 * Creates (or updates, on re-runs) everything Retell needs:
 *   voice LLM + voice agent, SMS LLM + chat agent, and the phone number's
 *   inbound/outbound bindings and webhooks.
 * IDs are written back to .env with --write-env so re-runs update in place.
 */
class RetellSetup extends Command
{
    protected $signature = 'retell:setup
        {--url= : Public HTTPS base URL Retell can reach (defaults to APP_URL)}
        {--number= : Retell phone number to bind, E.164 (defaults to RETELL_FROM_NUMBER)}
        {--voice= : Retell voice_id (defaults to RETELL_VOICE_ID)}
        {--write-env : Save the created IDs into .env}';

    protected $description = 'Create or update the Retell voice/SMS agents and bind them to the phone number';

    private const PII_CATEGORIES = [
        'person_name', 'address', 'email', 'phone_number', 'date_of_birth', 'medical_id',
        'customer_account_number', 'ssn', 'passport', 'driver_license', 'credit_card', 'bank_account',
    ];

    public function handle(RetellClient $retell, AgentContext $context): int
    {
        if (! $retell->hasApiKey()) {
            $this->components->error('Set RETELL_API_KEY in .env first (Retell dashboard → API Keys; use the key with the webhook badge).');

            return self::FAILURE;
        }

        $base = rtrim($this->option('url') ?: config('app.url'), '/');

        if (! str_starts_with($base, 'https://')) {
            $this->components->error("Retell needs a public HTTPS URL, got [{$base}]. Start a tunnel and pass --url=https://...");

            return self::FAILURE;
        }

        $voice = $this->option('voice') ?: config('services.retell.voice_id');
        $number = $this->option('number') ?: config('services.retell.from_number');
        $ids = [];

        try {
            $ids['RETELL_LLM_ID'] = $this->upsertLlm($retell, config('services.retell.llm_id'), [
                'general_prompt' => $this->prompt('voice'),
                'general_tools' => ToolDefinitions::all("{$base}/retell/functions", 'voice'),
                'start_speaker' => 'agent',
                // Works for inbound, outbound and browser calls alike.
                'begin_message' => 'Hi, this is the automated scheduling assistant for {{clinic_name}}. How can I help you today?',
                'default_dynamic_variables' => $context->dynamicVariables(),
            ]);

            $ids['RETELL_VOICE_AGENT_ID'] = $this->upsertAgent(
                fn (array $body) => $retell->createAgent($body),
                fn (string $id, array $body) => $retell->updateAgent($id, $body),
                config('services.retell.voice_agent_id'),
                [
                    'response_engine' => ['type' => 'retell-llm', 'llm_id' => $ids['RETELL_LLM_ID']],
                    'voice_id' => $voice,
                    'agent_name' => config('app.name').' (voice)',
                    'language' => 'en-US',
                    'timezone' => config('scheduling.timezone'),
                    'webhook_url' => "{$base}/retell/webhook",
                    'end_call_after_silence_ms' => 30000,
                    'max_call_duration_ms' => 15 * 60 * 1000,
                    ...$this->privacy(),
                ],
                'voice agent',
            );

            $ids['RETELL_SMS_LLM_ID'] = $this->upsertLlm($retell, config('services.retell.sms_llm_id'), [
                'general_prompt' => $this->prompt('sms'),
                'general_tools' => ToolDefinitions::all("{$base}/retell/functions", 'sms'),
                'start_speaker' => 'agent',
                'default_dynamic_variables' => $context->dynamicVariables(),
            ]);

            $ids['RETELL_SMS_AGENT_ID'] = $this->upsertAgent(
                fn (array $body) => $retell->createChatAgent($body),
                fn (string $id, array $body) => $retell->updateChatAgent($id, $body),
                config('services.retell.sms_agent_id'),
                [
                    'response_engine' => ['type' => 'retell-llm', 'llm_id' => $ids['RETELL_SMS_LLM_ID']],
                    'agent_name' => config('app.name').' (SMS)',
                    'language' => 'en-US',
                    'webhook_url' => "{$base}/retell/webhook",
                    ...$this->privacy(),
                ],
                'SMS chat agent',
            );

            if (filled($number)) {
                $retell->updatePhoneNumber($number, array_filter([
                    'inbound_agents' => [['agent_id' => $ids['RETELL_VOICE_AGENT_ID'], 'weight' => 1]],
                    'outbound_agents' => [['agent_id' => $ids['RETELL_VOICE_AGENT_ID'], 'weight' => 1]],
                    'inbound_webhook_url' => "{$base}/retell/inbound",
                    ...(config('services.retell.sms_enabled') ? [
                        'inbound_sms_agents' => [['agent_id' => $ids['RETELL_SMS_AGENT_ID'], 'weight' => 1]],
                        'outbound_sms_agents' => [['agent_id' => $ids['RETELL_SMS_AGENT_ID'], 'weight' => 1]],
                        'inbound_sms_webhook_url' => "{$base}/retell/inbound",
                    ] : []),
                ]));
                $this->components->info("Bound {$number} to the voice agent".(config('services.retell.sms_enabled') ? ' and SMS agent.' : '. (SMS binding skipped until RETELL_SMS_ENABLED=true.)'));
            } else {
                $this->components->warn('No phone number yet: buy one in the Retell dashboard, set RETELL_FROM_NUMBER and re-run to bind it. Browser test calls work without one.');
            }
        } catch (RequestException $e) {
            $message = $e->response->json('message') ?? $e->response->body();
            $this->components->error("Retell API error ({$e->response->status()}): {$message}");

            if (str_contains(strtolower((string) $message), 'voice')) {
                $this->line('  Pick a voice with --voice=<voice_id>; see GET https://api.retellai.com/list-voices.');
            }

            $this->writeIds($ids);

            return self::FAILURE;
        }

        $this->writeIds($ids);
        $this->components->info('Retell is set up. Open "Test AI agent" in the app to try it.');

        return self::SUCCESS;
    }

    private function upsertLlm(RetellClient $retell, ?string $existingId, array $body): string
    {
        $response = filled($existingId) ? $retell->updateRetellLlm($existingId, $body) : $retell->createRetellLlm($body);
        $this->components->twoColumnDetail(filled($existingId) ? 'Updated LLM' : 'Created LLM', $response['llm_id']);

        return $response['llm_id'];
    }

    private function upsertAgent(callable $create, callable $update, ?string $existingId, array $body, string $label): string
    {
        $response = filled($existingId) ? $update($existingId, $body) : $create($body);
        $this->components->twoColumnDetail((filled($existingId) ? 'Updated ' : 'Created ').$label, $response['agent_id']);

        return $response['agent_id'];
    }

    private function privacy(): array
    {
        return [
            'data_storage_setting' => 'everything_except_pii',
            'pii_config' => ['mode' => 'post_call', 'categories' => self::PII_CATEGORIES],
        ];
    }

    private function prompt(string $channel): string
    {
        return trim(file_get_contents(base_path("retell/prompts/{$channel}.md")));
    }

    private function writeIds(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        if (! $this->option('write-env')) {
            $this->newLine();
            $this->line('Add to .env (or re-run with --write-env):');
            foreach ($ids as $key => $value) {
                $this->line("  {$key}={$value}");
            }

            return;
        }

        $path = base_path('.env');
        $env = file_get_contents($path);

        foreach ($ids as $key => $value) {
            $line = "{$key}={$value}";
            $env = preg_match("/^{$key}=.*$/m", $env)
                ? preg_replace("/^{$key}=.*$/m", $line, $env)
                : rtrim($env).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($path, $env);
        $this->components->info('Saved IDs to .env.');
    }
}
