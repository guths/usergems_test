<?php

namespace App\Console\Commands;

use App\Jobs\SendEmail;
use App\Mail\EventMail;
use App\Models\DailyEmail;
use App\Models\Event;
use App\Models\Person;
use App\Services\CalendarApiService;
use App\Services\PersonalApiService;
use App\Services\PersonService;
use DateTime;
use DB;
use Exception;
use Illuminate\Console\Command;

class SendDailyEmailsCommand extends Command
{
    protected $signature = 'app:send-daily-emails';

    protected CalendarApiService $calendarService;

    protected PersonService $personService;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct()
    {
        parent::__construct();
        $this->calendarService = new CalendarApiService();
        $this->personService = new PersonService(new PersonalApiService);
    }
    
    public function handle()
    {
        $internalPeople = Person::query()
            ->where('enabled', true)
            ->where('is_internal', true)
            ->whereNotNull('api_key')
            ->get();

        foreach ($internalPeople as $internalPerson) {
            DB::transaction(function () use ($internalPerson) {
                $this->handleEvents($internalPerson);
            });
        }
    }

    public function handleEvents(Person $person) {
        $events = $this->getTodayEvents($person);

        if (empty($events)) {
            return;
        }

        foreach ($events as $e) {
            $event = $this->syncEvent($e);
            $event->people()->delete();

            if (empty($e['accepted']) && empty($e['rejected'])) {
                continue;
            }

            $this->addPeopleToEvent($event, $e['accepted'], 'accepted');
            $this->addPeopleToEvent($event, $e['rejected'], 'rejected');


            $payload = $this->buildPayloadEvent($person, $event);
            
            $dailyEmail = DailyEmail::create([
                'person_id' => $person->id,
            ]);

            try {
                SendEmail::dispatch($person, $dailyEmail, $payload);
            } catch (Exception $e) {
                $dailyEmail->update([
                    'status' => 'error',
                ]);
            }            
        }
    }

    public function syncEvent(array $data): Event {
        return Event::updateOrCreate([
            'calendar_api_id' => $data['id'],
        ], [
            'title' => $data['title'],
            'start_at' => $data['start'],
            'end_at' => $data['end'],
            'last_updated' => $data['changed']
        ]);
    }

    public function addPeopleToEvent(Event $event, array $participantEmails, string $status): void
    {
        foreach ($participantEmails as $participantEmail) {
            $person = $this->personService->addPersonToEvent($event, $participantEmail, $status);
            $this->personService->updatePersonInfo($person);
        }
    }

    public function buildPayloadEvent(Person $internalPerson, Event $event): array {
        $payload = [
            'start' => (new DateTime($event->start_at))->format('g:ia'),
            'end' => (new DateTime($event->end_at))->format('g:ia'),
            'joining_from_usergems' => "{$internalPerson->first_name} {$internalPerson->last_name}",
        ];

        $companies = [];
        $people = [];
        $internals = [];

        $involved = $event->people()->where('person_id', '!=', $internalPerson->id)->get();
        
        foreach ($involved as $person) {
            if (!empty($person->company->linkedin_url)) {
                $companies[$person->company->linkedin_url] = [
                    'name' => $person->company->name,
                    'employees' => $person->company->employees,
                    'linkedin_url' => $person->company->linkedin_url,
                ];
            }

            $p = [
                'email' => $person->email,
                'first_name' => $person->first_name,
                'last_name' => $person->last_name,
                'title' => $person->title,
                'avatar' => $person->avatar,
                'linkedin_url' => $person->linkedin_url,
                'quantity_of_meetings_before' => $this->personService->getQuantityOfMeetingsByInternalPerson($internalPerson, $person, $event),
                'other_sales_reps_meetings' => $this->personService->getInternalPeopleMeetings($internalPerson, $person, $event),
                'is_rejected' => $person->pivot->status === 'rejected',
            ];

            if ($person->is_internal) {
                $internals[] = $p;
            } else {
                $people[] = $p;
            }
        }
        $payload['companies'] = $companies;
        $payload['people'] = $people;
        $payload['internals'] = $internals;

        return $payload;
    }

    public function getTodayEvents(Person $person): array {
        $events = $this->calendarService->getEvents($person);

        if (empty($events)) {
            return [];
        }

        $today = now()->format('Y-m-d');

        return array_filter($events, function ($event) use ($today) {
            return (new DateTime($event['start']))->format('Y-m-d') === $today;
        });
    }
}
