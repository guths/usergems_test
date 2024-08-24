<?php

namespace App\Services;
use App\Models\Company;
use App\Models\Event;
use App\Models\Person;
class PersonService
{
    private PersonalApiService $personalApiService;

    public function __construct(PersonalApiService $personalApiService)
    {
        $this->personalApiService = $personalApiService;
    }

    public function updatePersonInfo(Person $person): Person|null
    {
        if ($person->is_internal) {
            return null;
        }

        $updatedPerson = null;

        if ($person->last_synced_at > now()->subMonths(1)) {
            return $person;
        }

        $updatedPerson = $this->personalApiService->getPersonalDataByEmail($person->email);

        if (empty($updatedPerson)) {
            return $person;
        }

        $company = Company::updateOrCreate([
            'linkedin_url' => $updatedPerson['company']['linkedin_url'],
        ], [
            'name' => $updatedPerson['company']['name'],
            'employees' => $updatedPerson['company']['employees'],
        ]);

        $person->update([
            'first_name' => $updatedPerson['first_name'],
            'last_name' => $updatedPerson['last_name'],
            'title' => $updatedPerson['title'],
            'avatar' => $updatedPerson['avatar'],
            'linkedin_url' => $updatedPerson['linkedin_url'],
            'last_synced_at' => now(),
            'company_id' => $company->id,
        ]);

        return $person;
    }

    public function addPersonToEvent(Event $event, string $personEmail, string $status): Person
    {
        $person = Person::firstOrCreate([
            'email' => $personEmail,
        ]);

        $event->people()->attach($person->id, [
            'status' => $status,
        ]);

        return $person;
    }

    public function getInternalPeopleMeetings(Person $internalPerson, Person $person): array
    {
        $internalPeople = Person::query()
            ->where('is_internal', true)
            ->where('id', '!=', $internalPerson->id)
            ->get();


        $response = [];

        foreach ($internalPeople as $iPerson) {
            $meetings = $iPerson->events()->whereHas('people', function ($query) use ($person) {
                $query->where('person_id', $person->id);
            })->count();

            if ($meetings > 0) {
                $response[$iPerson->first_name . " " . $iPerson->last_name] = $meetings;
            }
        }

        return $response;
    }

    public function getQuantityOfMeetingsByInternalPerson(Person $internalPerson, Person $person): int
    {
        return $internalPerson->events()->whereHas('people', function ($query) use ($person) {
            $query->where('person_id', $person->id);
        })->count();
    }
}