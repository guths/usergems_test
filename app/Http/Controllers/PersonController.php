<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonRequest;
use App\Models\Person;
use Illuminate\Http\Request;

class PersonController extends Controller
{
    public function store(PersonRequest $request)
    {
        $person = Person::create($request->validated());
        return response()->json($person, 201);
    }
}
