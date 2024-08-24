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

    protected Person $person;
    protected DailyEmail $research;
    protected array $payload;
    
    public function __construct(Person $person, DailyEmail $dailyEmail, array $payload)
    {
        $this->person = $person;
        $this->$dailyEmail = $dailyEmail;
        $this->payload = $payload;
    }

    public function handle(): void
    {
        try {
            Mail::to($this->person->email)->send(new EventMail($this->payload));
            $this->research->update([
                'status' => 'sent',
                'sended_at' => now(),
            ]);
        } catch (Exception $e) {
            $this->research->update([
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }
        
    }
}
