<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Person;
use App\Services\PersonalApiService;
use App\Services\PersonService;
use Http;
use Mockery;
use Tests\TestCase;

class PersonServiceTest extends TestCase
{
    public function test_add_person_to_event(): void {
        $this->withoutExceptionHandling();

        $person = Person::factory()->create();
        $event = Event::factory()->create();

        $personApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($personApiService);
        $person = $personService->addPersonToEvent($event, $person->email, 'accepted');

        $this->assertDatabaseHas('event_person', [
            'person_id' => $person->id,
            'event_id' => $event->id,
            'status' => 'accepted',
        ]);
    }

    public function test_get_internal_people_meeeting_events(): void {
        $this->withoutExceptionHandling();

        $internalPerson = Person::factory()->create([
        ]);

        $person = Person::factory()->create([
            'is_internal' => false,
        ]);

        $personOne = Person::factory()->create([
            'is_internal' => true,
        ]);

        $personTwo = Person::factory()->create([
            'is_internal' => true,
        ]);

        $event = Event::factory()->create();
        $eventTwo = Event::factory()->create();

        $event->people()->attach($personOne->id, [
            'status' => 'accepted',
        ]);

        $event->people()->attach($personTwo->id, [
            'status' => 'accepted',
        ]);

        $event->people()->attach($person->id, [
            'status' => 'accepted',
        ]);

        $eventTwo->people()->attach($personOne->id, [
            'status' => 'accepted',
        ]);

        $eventTwo->people()->attach($person->id, [
            'status' => 'accepted',
        ]);

        $personApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($personApiService);
        $result = $personService->getInternalPeopleMeetings($internalPerson, $person);

        $this->assertEquals($result[$personOne->first_name. " ". $personOne->last_name], 2);
        $this->assertEquals($result[$personTwo->first_name. " ". $personTwo->last_name], 1);
    }

    public function test_get_internal_people_meeting_events_with_no_internal_people(): void {
        $this->withoutExceptionHandling();

        $internalPerson = Person::factory()->create();

        $person = Person::factory()->create([
            'is_internal' => false,
        ]);

        $event = Event::factory()->create();

        $event->people()->attach($person->id, [
            'status' => 'accepted',
        ]);

        $personApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($personApiService);
        $result = $personService->getInternalPeopleMeetings($internalPerson, $person);

        $this->assertEquals($result, []);
    }

    public function test_get_internal_people_meeting_events_with_no_events(): void {
        $this->withoutExceptionHandling();

        $internalPerson = Person::factory()->create();

        $person = Person::factory()->create([
            'is_internal' => false,
        ]);

        Person::factory()->create([
            'is_internal' => true,
        ]);

        Person::factory()->create([
            'is_internal' => true,
        ]);

        $personApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($personApiService);
        $result = $personService->getInternalPeopleMeetings($internalPerson, $person);

        $this->assertEquals($result, []);
    }

    public function test_get_quantity_of_meetings_by_internal_person(): void {
        $this->withoutExceptionHandling();

        $internalPerson = Person::factory()->create();

        $person = Person::factory()->create([
            'is_internal' => false,
        ]);

        $event = Event::factory()->create();

        $event->people()->attach($person->id, [
            'status' => 'accepted',
        ]);

        $event->people()->attach($internalPerson->id, [
            'status' => 'accepted',
        ]);

        $personApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($personApiService);
        $result = $personService->getQuantityOfMeetingsByInternalPerson($internalPerson, $person);

        $this->assertEquals($result, 1);
    }

    public function test_get_quantity_of_meetings_by_internal_person_with_no_meetings(): void {
        $this->withoutExceptionHandling();

        $internalPerson = Person::factory()->create();

        $person = Person::factory()->create([
            'is_internal' => false,
        ]);

        $event = Event::factory()->create();

        $event->people()->attach($person->id, [
            'status' => 'accepted',
        ]);

        $personApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($personApiService);
        $result = $personService->getQuantityOfMeetingsByInternalPerson($internalPerson, $person);

        $this->assertEquals($result, 0);
    }

    public function test_try_update_person_info_that_is_internal(): void {
        $person = Person::factory()->create([
            'is_internal' => true,
        ]);

        $mockedPersonApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($mockedPersonApiService);

        $result = $personService->updatePersonInfo($person);

        $this->assertNull($result);
    }

    public function test_update_person_info_that_was_updated_in_last_month(): void {
        $person = Person::factory()->create([
            'is_internal' => false,
            'last_synced_at' => now()->subWeek(),
        ]);

        $mockedPersonApiService = Mockery::mock(PersonalApiService::class);

        $personService = new PersonService($mockedPersonApiService);

        $personResponse = $personService->updatePersonInfo($person);

        $this->assertNotNull($personResponse);

        $this->assertEquals($person->last_synced_at->format('Y-m-d'), $personResponse->last_synced_at->format('Y-m-d'));
    }

    public function test_update_person_that_has_not_response_from_api(): void {
        $person = Person::factory()->create([
            'is_internal' => false,
            'last_synced_at' => now()->subMonth(),
        ]);

        Http::fake([
            'https://app.usergems.com/api*' => Http::response([], 200) 
        ]);

        $personalApiService = new PersonalApiService();

        $personService = new PersonService($personalApiService);

        $personResponse = $personService->updatePersonInfo($person);

        $this->assertNotNull($personResponse);

        $this->assertEquals($person->last_synced_at->format('Y-m-d'), $personResponse->last_synced_at->format('Y-m-d'));
    }

    public function test_update_person_that_was_not_not_updated_in_last_month(): void {
        $person = Person::factory()->create([
            'is_internal' => false,
            'last_synced_at' => now()->subMonths(2),
        ]);

        $this->mock(PersonalApiService::class, function ($mock) use ($person) {
            $mock->shouldReceive('getPersonalDataByEmail')
                ->once()
                ->with($person->email)
                ->andReturn([
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'title' => 'Software Engineer',
                    'avatar' => 'https://example.com/avatar.jpg',
                    'linkedin_url' => 'https://linkedin.com/johndoe',
                    'company' => [
                        'name' => 'Example Company',
                        'employees' => 100,
                        'linkedin_url' => 'https://linkedin.com/examplecompany',
                    ],
                ]);
        });

        $personService = new PersonService(app(PersonalApiService::class));

        $personResponse = $personService->updatePersonInfo($person);

        $this->assertNotNull($personResponse);
        $this->assertEquals('John', $personResponse->first_name);
        $this->assertEquals('Doe', $personResponse->last_name);
        $this->assertEquals('Software Engineer', $personResponse->title);
        $this->assertEquals('https://example.com/avatar.jpg', $personResponse->avatar);
        $this->assertEquals('https://linkedin.com/johndoe', $personResponse->linkedin_url);
        $this->assertEquals('Example Company', $personResponse->company->name);
        $this->assertEquals(100, $personResponse->company->employees);
        $this->assertEquals('https://linkedin.com/examplecompany', $personResponse->company->linkedin_url);
    }
}
