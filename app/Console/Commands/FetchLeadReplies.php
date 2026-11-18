<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\InboundLeadReply;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

/**
 * Lee los buzones de conversación por IMAP (uno por empresa configurada, más el
 * global del .env como respaldo) y agrega las respuestas de los leads al hilo.
 * Pensado para correr por cron cada pocos minutos.
 */
class FetchLeadReplies extends Command
{
    protected $signature = 'leads:fetch-replies {--limit=50 : Máximo de correos por buzón}';

    protected $description = 'Lee los buzones por IMAP y agrega las respuestas de los leads a su conversación';

    public function handle(InboundLeadReply $inbound): int
    {
        $accounts = $this->accounts();

        if (empty($accounts)) {
            $this->warn('Ninguna empresa (ni el .env) tiene IMAP configurado.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($accounts as $account) {
            $total += $this->poll($account, $inbound);
        }

        $this->info("Respuestas agregadas al hilo: {$total}");

        return self::SUCCESS;
    }

    /**
     * Buzones a leer: cada empresa con IMAP listo + el global del .env.
     *
     * @return array<int, array{label: string, company_id: int|null, config: array<string, mixed>}>
     */
    protected function accounts(): array
    {
        $accounts = [];

        foreach (Company::query()->where('conversations_enabled', true)->get() as $company) {
            if ($company->conversationsReady()) {
                $accounts[] = [
                    'label' => $company->name,
                    'company_id' => $company->id,
                    'folder' => $company->imap_folder ?: 'INBOX',
                    'config' => $company->imapConfig(),
                ];
            }
        }

        $cfg = config('services.leads_mailbox');
        if (filled($cfg['imap_username']) && filled($cfg['imap_password'])) {
            $accounts[] = [
                'label' => 'global (.env)',
                'company_id' => null,
                'folder' => $cfg['imap_folder'] ?: 'INBOX',
                'config' => [
                    'host' => $cfg['imap_host'],
                    'port' => $cfg['imap_port'],
                    'encryption' => $cfg['imap_encryption'] ?: false,
                    'validate_cert' => $cfg['imap_validate_cert'],
                    'username' => $cfg['imap_username'],
                    'password' => $cfg['imap_password'],
                    'protocol' => 'imap',
                ],
            ];
        }

        return $accounts;
    }

    /**
     * @param  array{label: string, company_id: int|null, config: array<string, mixed>}  $account
     */
    protected function poll(array $account, InboundLeadReply $inbound): int
    {
        try {
            $client = (new ClientManager)->make($account['config']);
            $client->connect();
        } catch (\Throwable $e) {
            $this->error("[{$account['label']}] no se pudo conectar: ".$e->getMessage());

            return 0;
        }

        $folder = $client->getFolder($account['folder'] ?? 'INBOX');
        $messages = $folder->query()->unseen()->limit((int) $this->option('limit'))->get();

        $processed = 0;

        foreach ($messages as $message) {
            try {
                $to = [];
                foreach ($message->getTo() ?? [] as $address) {
                    $to[] = (string) $address->mail;
                }
                foreach ($message->getCc() ?? [] as $address) {
                    $to[] = (string) $address->mail;
                }
                $deliveredTo = $message->getHeader()?->get('delivered_to');
                if ($deliveredTo) {
                    foreach ((array) $deliveredTo->toArray() as $value) {
                        $to[] = (string) $value;
                    }
                }

                $from = '';
                foreach ($message->getFrom() ?? [] as $address) {
                    $from = (string) $address->mail;
                    break;
                }

                $body = trim((string) $message->getTextBody());
                if ($body === '') {
                    $body = trim(strip_tags((string) $message->getHTMLBody()));
                }

                $stored = $inbound->record(
                    $to,
                    $from,
                    (string) $message->getSubject(),
                    $body,
                    (string) $message->getMessageId() ?: null,
                    (string) $message->getInReplyTo() ?: null,
                    $account['company_id'],
                );

                if ($stored) {
                    $processed++;
                }

                $message->setFlag('Seen');
            } catch (\Throwable $e) {
                $this->warn("[{$account['label']}] mensaje omitido: ".$e->getMessage());
            }
        }

        return $processed;
    }
}
