<?php 
namespace App\Interfaces;

use App\Models\People;

interface PersonalInterface { 
    public function getPersonalDataByEmail(string $email): array;
}