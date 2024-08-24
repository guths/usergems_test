<?php

namespace App\Services;

use App\Interfaces\CalendarInterface;
use App\Models\Event;
use App\Models\Person;
use Exception;
use Http;
class CalendarApiService implements CalendarInterface
{
    private $url = 'https://app.usergems.com/api';

    public function getEvents(Person $person): array
    {
        $events = [];
        $currentPage = 1;

        $lastUpdatedEvent = Event::query()->whereHas('people', function ($query) use ($person) {
            $query->where('people.id', $person->id);
        })->latest('last_updated')->first();
        
        while (true) {
            $response = Http::withHeaders(['Authorization' => "Bearer {$person->api_token}"])
                ->get("{$this->url}/hiring/calendar-challenge/events", [
                    'page' => $currentPage,
                ]);

            if ($response->failed()) {
                throw new Exception('Failed to get events from calendar API');
            }

            $pageEvents = $response->json('data');

            if ($lastUpdatedEvent) {
                $pageEvents = array_filter($pageEvents, function ($event) use ($lastUpdatedEvent) {
                    return $event['changed'] > $lastUpdatedEvent->last_updated;
                });
            }

            if (empty($pageEvents)) {
                break;
            }

            array_push($events, ...$pageEvents);

            $currentPage++;
        }

        return $events;
    }

}