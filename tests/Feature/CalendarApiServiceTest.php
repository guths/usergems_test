<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Services\CalendarApiService;
use DateTime;
use Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CalendarApiServiceTest extends TestCase
{
    public function test_if_get_events_throw_exception_when_token_is_invalid()
    {
        $this->withoutExceptionHandling();

        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $calendarApiService = new CalendarApiService();

        try {
            $calendarApiService->getEvents($person);
        } catch (\Exception $e) {
            $this->assertEquals('Failed to get events from calendar API', $e->getMessage());
        }
    }

    public function test_get_events_with_empty_events()
    {

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=1' => Http::response([
                'data' => []
            ]),
        ]);

        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $calendarApiService = new CalendarApiService();

        $events = $calendarApiService->getEvents($person);

        $this->assertEmpty($events);
    }

    public function test_get_events_with_pagination()
    {
        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=1' => Http::response([
                'data' => [
                    [
                        'start' => (new DateTime())->format('Y-m-d') . 'T00:00:00Z',
                    ],
                    [
                        'start' => (new DateTime())->format('Y-m-d') . 'T00:00:00Z',
                    ],
                    [
                        'start' => '2021-01-01T00:00:00Z',
                    ]
                ]
            ], 200)
        ]);

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=2' => Http::response([
                'data' => [
                ]
            ], 200)
        ]);

        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $calendarApiService = new CalendarApiService();

        $events = $calendarApiService->getEvents($person);
        $this->assertCount(3, $events);
    }

    public function test_get_events_with_last_updated_event()
    {
        $lastUpdatedEvent = Event::factory()->create([
            'last_updated' => '2021-01-01 00:00:00',
        ]);

        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $person->events()->attach($lastUpdatedEvent);

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=1' => Http::response([
                'data' => [
                    [
                        'start' => (new DateTime())->format('Y-m-d') . 'T00:00:00Z',
                        'changed' => '2021-01-02 00:00:00',
                    ],
                    [
                        'start' => (new DateTime())->format('Y-m-d') . 'T00:00:00Z',
                        'changed' => '2021-01-02 00:00:00',
                    ],
                    [
                        'start' => '2021-01-01T00:00:00Z',
                        'changed' => '2021-01-01 00:00:00',
                    ]
                ]
            ], 200)
        ]);

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=2' => Http::response([
                'data' => [
                ]
            ], 200)
        ]);


        $calendarApiService = new CalendarApiService();

        $events = $calendarApiService->getEvents($person);
        $this->assertCount(2, $events);
    }
}
