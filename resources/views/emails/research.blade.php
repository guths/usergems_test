<x-mail::message>
<h1 style="font-size: 24px; color: #4A90E2;">Meeting Schedule</h1>

<p><strong>{{$start}} {{$end}}</strong> </p>

@foreach ($companies as $company)
<strong>UserGems x {{$company['name']}} ({{$company['employees']}} employees)</strong> (30 min)
@endforeach

<p>Joining from UserGems: <strong>{{$joining_from_usergems}}</strong></p>

<hr style="border: 1px solid #e9ecef;">

@foreach ($people as $person)
<h3 style="margin: 10px 0; color: #333;">{{$person['first_name']}} {{$person['last_name']}}</h3>
<p style="margin: 5px 0;">{{$person['title']}}</p>
<hr style="border: 1px solid #e9ecef;">
@endforeach

</x-mail::message>