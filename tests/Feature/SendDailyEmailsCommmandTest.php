<?php

namespace Tests\Feature;

use App\Console\Commands\SendDailyEmailsCommand;
use App\Models\Company;
use App\Models\Event;
use App\Models\Person;
use DateTime;
use Http;
use Tests\TestCase;

class SendDailyEmailsCommmandTest extends TestCase
{
    public function test_get_today_events(): void
    {
        $this->withoutExceptionHandling();

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=1' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'title' => 'Test Event',
                        'start' => now()->format('Y-m-d'),
                        'end' => now()->format('Y-m-d'),
                        'changed' => now()->format('Y-m-d'),
                    ],
                    [
                        'id' => 2,
                        'title' => 'Test Event 2',
                        'start' => now()->subDay()->format('Y-m-d'),
                        'end' => now()->subDay()->format('Y-m-d'),
                        'changed' => now()->subDay()->format('Y-m-d'),
                    ]
                ],
            ]),
        ]);

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=2' => Http::response([
                'data' => [],
            ]),
        ]);

        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $command = new SendDailyEmailsCommand();

        $events = $command->getTodayEvents($person);

        $this->assertCount(1, $events);
    }

    public function test_sync_event(): void {
        $data = [
            'id' => 1,
            'title' => 'Test Event',
            'start' => now()->format('Y-m-d'),
            'end' => now()->format('Y-m-d'),
            'changed' => now()->format('Y-m-d'),
        ];

        $command = new SendDailyEmailsCommand();

        $event = $command->syncEvent($data);

        $this->assertDatabaseHas('events', [
            'calendar_api_id' => 1,
            'title' => 'Test Event',
            'start_at' => now()->format('Y-m-d'),
            'end_at' => now()->format('Y-m-d'),
            'last_updated' => now()->format('Y-m-d'),
        ]);

        $this->assertInstanceOf(Event::class, $event);
    }

    public function test_add_people_to_event(): void {
        $event = Event::factory()->create();

        $personOne = Person::factory()->create([
            'is_internal' => false,
        ]);
        $personTwo = Person::factory()->create([
            'is_internal' => false,
        ]);


        $command = new SendDailyEmailsCommand();

        $command->addPeopleToEvent($event, [$personOne->email, $personTwo->email], 'accepted');

        $this->assertDatabaseHas('event_person', [
            'event_id' => $event->id,
            'person_id' => $personOne->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseHas('event_person', [
            'event_id' => $event->id,
            'person_id' => $personTwo->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseCount('people', 2);

    }

    public function test_build_payload_event(): void {
        $internalPerson = Person::factory()->create([
            'is_internal' => true,
        ]);

        $company = Company::factory()->create();

        $personOne = Person::factory()->create([
            'is_internal' => false
        ]);

        $personTwo = Person::factory()->create([
            'is_internal' => false
        ]);

        $personOne->company()->associate($company);
        $personOne->save();

        $personTwo->company()->associate($company);
        $personTwo->save();


        $event = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $event->people()->attach($internalPerson, ['status' => 'accepted']);
        $event->people()->attach($personOne, ['status' => 'accepted']);
        $event->people()->attach($personTwo, ['status' => 'accepted']);
        $command = new SendDailyEmailsCommand();

        $payload = $command->buildPayloadEvent($internalPerson, $event);

        $this->assertIsArray($payload);
        $this->assertEquals((new DateTime($event->start_at))->format('g:ia'), $payload['start']);
        $this->assertEquals((new DateTime($event->end_at))->format('g:ia'), $payload['end']);
        $this->assertEquals($internalPerson->first_name . ' ' . $internalPerson->last_name, $payload['joining_from_usergems']);
        $this->assertCount(2, $payload['people']);
        $this->assertEquals($personOne->email, $payload['people'][0]['email']);
        $this->assertEquals($personTwo->email, $payload['people'][1]['email']);
        $this->assertEquals($personOne->first_name, $payload['people'][0]['first_name']);
        $this->assertEquals($personTwo->first_name, $payload['people'][1]['first_name']);
        $this->assertEquals($personOne->last_name, $payload['people'][0]['last_name']);
        $this->assertEquals($personTwo->last_name, $payload['people'][1]['last_name']);
        $this->assertEquals($personOne->title, $payload['people'][0]['title']);
        $this->assertEquals($personTwo->title, $payload['people'][1]['title']);
        $this->assertEquals($personOne->avatar, $payload['people'][0]['avatar']);
        $this->assertEquals($personTwo->avatar, $payload['people'][1]['avatar']);
        $this->assertEquals($personOne->linkedin_url, $payload['people'][0]['linkedin_url']);
        $this->assertEquals($personTwo->linkedin_url, $payload['people'][1]['linkedin_url']);
        $this->assertEquals(0, $payload['people'][0]['quantity_of_meetings_before']);
        $this->assertEquals(0, $payload['people'][1]['quantity_of_meetings_before']);
        $this->assertEquals([], $payload['people'][0]['other_sales_reps_meetings']);
        $this->assertEquals([], $payload['people'][1]['other_sales_reps_meetings']);
        $this->assertEquals($company->linkedin_url, $payload['companies'][$company->linkedin_url]['linkedin_url']);
        $this->assertEquals($company->name, $payload['companies'][$company->linkedin_url]['name']);
        $this->assertEquals($company->employees, $payload['companies'][$company->linkedin_url]['employees']);
    }

    public function test_build_payload_with_rejected_person(): void {
        $internalPerson = Person::factory()->create([
            'is_internal' => true,
        ]);

        $company = Company::factory()->create();

        $personOne = Person::factory()->create([
            'is_internal' => false,
        ]);
        $personTwo = Person::factory()->create([
            'is_internal' => false,
        ]);

        $personOne->company()->associate($company);
        $personOne->save();

        $personTwo->company()->associate($company);
        $personTwo->save();


        $event = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $event->people()->attach($internalPerson, ['status' => 'accepted']);
        $event->people()->attach($personOne, ['status' => 'accepted']);
        $event->people()->attach($personTwo, ['status' => 'rejected']);

        $command = new SendDailyEmailsCommand();

        $payload = $command->buildPayloadEvent($internalPerson, $event);
        $this->assertIsArray($payload);
        $this->assertEquals((new DateTime($event->start_at))->format('g:ia'), $payload['start']);
        $this->assertEquals((new DateTime($event->end_at))->format('g:ia'), $payload['end']);
        $this->assertEquals($internalPerson->first_name . ' ' . $internalPerson->last_name, $payload['joining_from_usergems']);
        $this->assertCount(2, $payload['people']);
        $this->assertEquals(false, $payload['people'][0]['is_rejected']);
        $this->assertEquals(true, $payload['people'][1]['is_rejected']);
    }

    public function test_build_payload_with_internal_people_that_already_met_some_person_of_the_event(): void {
        $internalPerson = Person::factory()->create([
            'is_internal' => true,
        ]);

        $otherInternalOne = Person::factory()->create([
            'is_internal' => true,
        ]);

        $otherInternalTwo = Person::factory()->create([
            'is_internal' => true
        ]);

        $company = Company::factory()->create();

        $personOne = Person::factory()->create([
            'is_internal' => false,
        ]);
        $personTwo = Person::factory()->create([
            'is_internal' => false,
        ]);

        $personOne->company()->associate($company);
        $personOne->save();

        $personTwo->company()->associate($company);
        $personTwo->save();


        $event = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $event->people()->attach($internalPerson, ['status' => 'accepted']);
        $event->people()->attach($personOne, ['status' => 'accepted']);
        $event->people()->attach($personTwo, ['status' => 'rejected']);

        $oldEvent = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $oldEvent->people()->attach($otherInternalOne, ['status' => 'accepted']);
        $oldEvent->people()->attach($personOne, ['status' => 'accepted']);
        $oldEvent->people()->attach($otherInternalTwo, ['status' => 'accepted']);

        $oldoldEvent = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $oldoldEvent->people()->attach($otherInternalOne, ['status' => 'accepted']);
        $oldoldEvent->people()->attach($personOne, ['status' => 'accepted']);

        $command = new SendDailyEmailsCommand();

        $payload = $command->buildPayloadEvent($internalPerson, $event);
        $this->assertIsArray($payload);
        $this->assertEquals((new DateTime($event->start_at))->format('g:ia'), $payload['start']);
        $this->assertEquals((new DateTime($event->end_at))->format('g:ia'), $payload['end']);
        $this->assertEquals($internalPerson->first_name . ' ' . $internalPerson->last_name, $payload['joining_from_usergems']);
        $this->assertCount(2, $payload['people']);
        $this->assertEquals(0, $payload['people'][0]['quantity_of_meetings_before']);
        $this->assertEquals(0, $payload['people'][1]['quantity_of_meetings_before']);
        $this->assertCount(2, $payload['people'][0]['other_sales_reps_meetings']);
        $this->assertCount(0, $payload['people'][1]['other_sales_reps_meetings']);
        $this->assertEquals(2, $payload['people'][0]['other_sales_reps_meetings'][$otherInternalOne->first_name . " ". $otherInternalOne->last_name]);
        $this->assertEquals(1, $payload['people'][0]['other_sales_reps_meetings'][$otherInternalTwo->first_name . " ". $otherInternalTwo->last_name]);
    }

    public function test_build_payload_with_current_internal_that_already_met_some_people_of_the_event(): void {
        $internalPerson = Person::factory()->create([
            'is_internal' => true,
        ]);

        $company = Company::factory()->create();

        $personOne = Person::factory()->create([
            'is_internal' => false,
        ]);
        $personTwo = Person::factory()->create([
            'is_internal' => false,
        ]);

        $personOne->company()->associate($company);
        $personOne->save();

        $personTwo->company()->associate($company);
        $personTwo->save();


        $event = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $event->people()->attach($personOne, ['status' => 'accepted']);
        $event->people()->attach($internalPerson, ['status' => 'accepted']);
        $event->people()->attach($personTwo, ['status' => 'accepted']);

        $oldEvent = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $oldEvent->people()->attach($personOne, ['status' => 'accepted']);
        $oldEvent->people()->attach($internalPerson, ['status' => 'accepted']);
        $oldEvent->people()->attach($personTwo, ['status' => 'accepted']);

        $oldoldEvent = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $oldoldEvent->people()->attach($personOne, ['status' => 'accepted']);
        $oldoldEvent->people()->attach($internalPerson, ['status' => 'accepted']);
        $oldoldEvent->people()->attach($personTwo, ['status' => 'accepted']);

        $command = new SendDailyEmailsCommand();

        $payload = $command->buildPayloadEvent($internalPerson, $event);

        $this->assertIsArray($payload);
        $this->assertEquals((new DateTime($event->start_at))->format('g:ia'), $payload['start']);
        $this->assertEquals((new DateTime($event->end_at))->format('g:ia'), $payload['end']);
        $this->assertEquals($internalPerson->first_name . ' ' . $internalPerson->last_name, $payload['joining_from_usergems']);
        $this->assertCount(2, $payload['people']);
        $this->assertEquals(2, $payload['people'][0]['quantity_of_meetings_before']);
        $this->assertEquals(2, $payload['people'][1]['quantity_of_meetings_before']);
    }

    public function test_build_payload_with_two_other_internal_in_same_event(): void {
        $internalPerson = Person::factory()->create([
            'is_internal' => true,
        ]);

        $otherInternalOne = Person::factory()->create([
            'is_internal' => true,
        ]);

        $otherInternalTwo = Person::factory()->create([
            'is_internal' => true
        ]);

        $internalCompany = Company::factory()->create([
            'name' => 'User Gems Internal'
        ]);
        $company = Company::factory()->create();

        $personOne = Person::factory()->create([
            'is_internal' => false,
        ]);

        $personTwo = Person::factory()->create([
            'is_internal' => false,
        ]);

        $otherInternalOne->company()->associate($internalCompany);
        $otherInternalOne->save();

        $otherInternalTwo->company()->associate($internalCompany);
        $otherInternalTwo->save();

        $personOne->company()->associate($company);
        $personOne->save();

        $personTwo->company()->associate($company);
        $personTwo->save();

        $event = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $event->people()->attach($internalPerson, ['status' => 'accepted']);
        $event->people()->attach($otherInternalOne, ['status' => 'accepted']);
        $event->people()->attach($otherInternalTwo, ['status' => 'accepted']);
        $event->people()->attach($personOne, ['status' => 'accepted']);
        $event->people()->attach($personTwo, ['status' => 'rejected']);

        $oldEvent = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $oldEvent->people()->attach($otherInternalOne, ['status' => 'accepted']);
        $oldEvent->people()->attach($personOne, ['status' => 'accepted']);
        $oldEvent->people()->attach($otherInternalTwo, ['status' => 'accepted']);
        $oldEvent->people()->attach($internalPerson, ['status' => 'accepted']);

        $oldoldEvent = Event::factory()->create([
            'start_at' => now()->format('Y-m-d H:i:s'),
            'end_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'title' => 'Test Event',
        ]);

        $oldoldEvent->people()->attach($otherInternalOne, ['status' => 'accepted']);
        $oldoldEvent->people()->attach($personOne, ['status' => 'accepted']);
        $oldoldEvent->people()->attach($otherInternalTwo, ['status' => 'accepted']);


        $command = new SendDailyEmailsCommand();

        $payload = $command->buildPayloadEvent($internalPerson, $event);

        $this->assertIsArray($payload);
        $this->assertEquals((new DateTime($event->start_at))->format('g:ia'), $payload['start']);
        $this->assertEquals((new DateTime($event->end_at))->format('g:ia'), $payload['end']);
        $this->assertEquals($internalPerson->first_name . ' ' . $internalPerson->last_name, $payload['joining_from_usergems']);
        $this->assertCount(2, $payload['people']);
        $this->assertCount(2, $payload['internals']);
        $this->assertEquals(1, $payload['people'][0]['quantity_of_meetings_before']);
        $this->assertEquals(0, $payload['people'][1]['quantity_of_meetings_before']);
        $this->assertEquals(1, $payload['internals'][0]['quantity_of_meetings_before']);
        $this->assertEquals(1, $payload['internals'][1]['quantity_of_meetings_before']);
    }

    public function test_complete_handle_events(): void
    {
        $this->withoutExceptionHandling();

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/person/johndoe@gmail.com' => Http::response([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'johndoe@gmail.com',
                'title' => 'Software Engineer',
                'avatar' => 'https://example.com/avatar.jpg',
                'linkedin_url' => 'https://linkedin.com/johndoe',
                'company' => [
                    'name' => 'Example Company',
                    'employees' => 100,
                    'linkedin_url' => 'https://linkedin.com/company/example',
                ],
            ]),
        ]);

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/person/alice@gmail.com' => Http::response([
                'first_name' => 'Alice',
                'last_name' => 'Doe',
                'email' => 'alice@gmail.com',
                'title' => 'Software Engineer',
                'avatar' => 'https://example.com/avatar.jpg',
                'linkedin_url' => 'https://linkedin.com/alice',
                'company' => [
                    'name' => 'Example Company',
                    'employees' => 100,
                    'linkedin_url' => 'https://linkedin.com/company/example',
                ],
            ]),
        ]);


        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=1' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'title' => 'Test Event',
                        'start' => now()->format('Y-m-d'),
                        'end' => now()->format('Y-m-d'),
                        'changed' => now()->format('Y-m-d'),
                        'accepted' => [
                            'johndoe@gmail.com',
                            'alice@gmail.com',
                        ],
                        'rejected' => [],
                    ],
                    [
                        'id' => 2,
                        'title' => 'Test Event 2',
                        'start' => now()->subDay()->format('Y-m-d'),
                        'end' => now()->subDay()->format('Y-m-d'),
                        'changed' => now()->subDay()->format('Y-m-d'),
                    ]
                ],
            ]),
        ]);

        Http::fake([
            'https://app.usergems.com/api/hiring/calendar-challenge/events?page=2' => Http::response([
                'data' => [],
            ]),
        ]);

        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $command = new SendDailyEmailsCommand();

        $command->handle();

        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('people', 3);

        $this->assertDatabaseHas('daily_emails', [
            'person_id' => $person->id,
            'status' => 'sent',
        ]);
    }
}
