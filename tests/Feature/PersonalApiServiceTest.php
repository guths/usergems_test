<?php

namespace Tests\Feature;

use App\Services\PersonalApiService;
use Config;
use Exception;
use Http;
use Tests\TestCase;

class PersonalApiServiceTest extends TestCase
{


    public function test_if_personal_api_service_throw_exception_with_wrong_token(): void
    {
        $this->withoutExceptionHandling();
        Config::set('services.personal_api.token', 'xxxxx');

        $service = new PersonalApiService();

        try {
            $service->getPersonalDataByEmail('demi@algolia.com');
        } catch (Exception $e) {
            $this->assertEquals('Failed to get personal data', $e->getMessage());
        }
    }

    public function test_if_personal_api_service_throw_exception_when_status_code_is_not_200(): void
    {
        $this->withoutExceptionHandling();
        
        Http::fake([
            'https://app.usergems.com/api*' => Http::response([
            ], 400) 
        ]);

        $service = new PersonalApiService();

        try {
            $service->getPersonalDataByEmail('demi@algolia.com');
        } catch (Exception $e) {
            $this->assertEquals('Failed to get personal data', $e->getMessage());
        }
    }
}
