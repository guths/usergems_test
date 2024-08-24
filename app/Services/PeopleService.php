<?php

namespace App\Services;
use App\Models\Company;
use App\Models\Person;
class PeopleService
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

        $threeMonthsAgo = now()->subMonths(3);

        $updatedPerson = null;

        if ($person?->last_synced_at > $threeMonthsAgo) {
            return $person;
        }

        $updatedPerson = $this->personalApiService->getPersonalDataByEmail($person->email);

        if (empty($updatedPerson)) {
            return $person;
        }

        $company = Company::firstOrCreate([
            'name' => $updatedPerson['company'],
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
}