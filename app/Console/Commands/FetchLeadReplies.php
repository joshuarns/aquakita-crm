<?php

namespace App\Console\Commands;

use App\Support\InboundLeadReply;
use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;

/**
 * Lee el buzón de conversación por IMAP y agrega las respuestas de los leads
 * al hilo. Pensado para correr por cron cada pocos minutos.
 */
class FetchLeadReplies extends Command
{
    protected $signature = 'leads:fetch-replies {--limit=50 : Máximo de correos a procesar}';

    protected $description = 'Lee el buzón por IMAP y agrega las respuestas de los leads a su conversación';

    public function handle(InboundLeadReply $inbound): int
    {
        $cfg = config('services.leads_mailbox');

        if (blank($cfg['imap_username']) || blank($cfg['imap_password'])) {
            $this->warn('IMAP no configurado (define LEADS_IMAP_USERNAME y LEADS_IMAP_PASSWORD).');

            return self::SUCCESS;
        }

        $client = (new ClientManager)->make([
            'host' => $cfg['imap_host'],
            'port' => $cfg['imap_port'],
            'encryption' => $cfg['imap_encryption'] ?: false,
            'validate_cert' => $cfg['imap_validate_cert'],
            'username' => $cfg['imap_username'],
            'password' => $cfg['imap_password'],
            'protocol' => 'imap',
        ]);

        try {
            $client->connect();
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar al IMAP: '.$e->getMessage());

            return self::FAILURE;
        }

        $folder = $client->getFolder($cfg['imap_folder'] ?: 'INBOX');
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
                // Delivered-To suele conservar el +token aunque To venga reescrito.
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
                );

                if ($stored) {
                    $processed++;
                }

                $message->setFlag('Seen');
            } catch (\Throwable $e) {
                $this->warn('Mensaje omitido: '.$e->getMessage());
            }
        }

        $this->info("Respuestas agregadas al hilo: {$processed}");

        return self::SUCCESS;
    }
}
