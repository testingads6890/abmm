<?php

namespace App\Jobs;

use App\Services\Messaging\MessageService;
use App\Services\{AudienceService, PhoneService};
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/** Sends one message to every family in a target (nation / country / state / district / city / zone) on WhatsApp, SMS or e-mail. */
class SendBroadcast implements ShouldQueue
{
    use Queueable;
    public int $timeout = 3600;

    public function __construct(public array $target, public string $channel, public string $category, public string $text, public ?string $template = null, public ?string $subject = null) {}

    public function handle(AudienceService $aud, MessageService $msg): void
    {
        $q = $aud->families($this->target);
        $q->orderBy('id')->chunkById(200, function ($families) use ($msg) {
            foreach ($families as $f) {
                $to = PhoneService::e164($f->mobile, $f->country_code ?: '+91');
                match ($this->channel) {
                    'whatsapp' => $msg->whatsapp($to, (string) $this->template, [$f->head_name, $this->text], $this->category, null, null, $f->id),
                    'sms' => $msg->sms($to, $this->text, $this->category, $f->id),
                    'email' => $f->email ? $msg->email($f->email, $this->subject ?: 'ABMM Sampark', $this->text, null, null, $f->id) : null,
                };
            }
        });
    }
}
