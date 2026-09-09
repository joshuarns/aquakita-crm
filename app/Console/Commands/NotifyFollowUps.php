<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\LeadNotification;
use App\Support\LeadNotifier;
use Illuminate\Console\Command;

/**
 * Genera avisos de seguimiento próximo (hoy) y seguimiento vencido (§4.3).
 * Pensado para ejecutarse a diario. Evita duplicar el mismo aviso en el día.
 */
class NotifyFollowUps extends Command
{
    protected $signature = 'leads:notify-followups';

    protected $description = 'Notifica a los vendedores sus seguimientos próximos y vencidos';

    public function handle(LeadNotifier $notifier): int
    {
        $sent = 0;

        // Seguimientos programados para hoy, aún por atender.
        $upcoming = Activity::query()
            ->with('lead.vendor')
            ->where('completed', false)
            ->whereNotNull('follow_up_at')
            ->whereDate('follow_up_at', today())
            ->get();

        foreach ($upcoming as $activity) {
            $sent += $this->notifyOnce($notifier, $activity, 'seguimiento_proximo');
        }

        // Seguimientos vencidos (fecha pasada, siguen pendientes) (§6).
        $overdue = Activity::query()->with('lead.vendor')->overdue()->get();

        foreach ($overdue as $activity) {
            $sent += $this->notifyOnce($notifier, $activity, 'seguimiento_vencido');
        }

        $this->info("Avisos enviados: {$sent}");

        return self::SUCCESS;
    }

    /**
     * Envía el aviso salvo que ya se haya enviado uno del mismo tipo hoy
     * para ese lead (evita duplicados si el comando corre varias veces).
     */
    private function notifyOnce(LeadNotifier $notifier, Activity $activity, string $type): int
    {
        $lead = $activity->lead;
        $vendor = $lead?->vendor;

        if (! $vendor) {
            return 0;
        }

        $already = LeadNotification::where('lead_id', $lead->id)
            ->where('vendor_id', $vendor->id)
            ->where('type', $type)
            ->whereDate('created_at', today())
            ->exists();

        if ($already) {
            return 0;
        }

        $notifier->notify($lead, $vendor, $type, [
            'fecha' => $activity->follow_up_at->format('d/m/Y H:i'),
        ]);

        return 1;
    }
}
