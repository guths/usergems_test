<?php

namespace App\Jobs;

use App\Mail\EventMail;
use App\Mail\ResearchMail;
use App\Models\DailyEmail;
use App\Models\Person;
use App\Models\Research;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(private Person $person, private DailyEmail $dailyEmail, private array $payload)
    {
    }

    public function handle(): void
    {
        try {
            Mail::to($this->person->email)->send(new EventMail($this->payload));
            $this->dailyEmail->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Exception $e) {
            $this->dailyEmail->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }

    }
}
