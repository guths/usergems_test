<?php

namespace App\Services;
use App\Interfaces\PersonalInterface;
use Exception;
use Http;

class PersonalApiService implements PersonalInterface {
    private $url = 'https://app.usergems.com/api';

    public function getPersonalDataByEmail(string $email): array {
        $token = config('services.personal_api.token');

        $request = Http::withHeaders([
            'Authorization' => "Bearer $token"
        ]);

        $response = $request->get($this->url . '/hiring/calendar-challenge/person/'. $email);
        
        if ($response->failed()) {
            throw new Exception('Failed to get personal data');
        }

        return $response->json();
    }
}