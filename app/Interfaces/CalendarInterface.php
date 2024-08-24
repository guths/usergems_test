<?php

namespace App\Interfaces;

use App\Models\Person;
use DateTime;

interface CalendarInterface {
    function getEvents(Person $person): array;
}