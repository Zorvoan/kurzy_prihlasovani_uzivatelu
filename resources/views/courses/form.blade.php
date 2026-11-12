@extends('layout') {{-- použijeme základní rozvržení --}}
@section('title', $course->exists ? 'Upravit kurz' : 'Nový kurz') {{-- titulek podle toho, zda kurz už existuje --}}

@section('content') {{-- začátek obsahu --}}
<h1>{{ $course->exists ? 'Upravit kurz' : 'Nový kurz' }}</h1> {{-- nadpis --}}

<form method="post" action="{{ $course->exists ? route('courses.update', $course) : route('courses.store') }}"> {{-- úprava -> update, nový -> store --}}
    @csrf {{-- skrytý token proti útoku CSRF (Laravel ho vyžaduje u každého POST formuláře) --}}
    @if ($course->exists) @method('PUT') @endif {{-- HTML umí jen GET/POST, takže PUT pro úpravu "podvrhneme" skrytým polem --}}
    <label>Název <input name="name" value="{{ old('name', $course->name) }}" required></label> {{-- old() vrátí hodnotu zadanou před chybou validace --}}
    <label>Kód <input name="code" value="{{ old('code', $course->code) }}" required></label> {{-- kód kurzu (jedinečný) --}}
    <label>Kapacita <input name="capacity" type="number" min="1" step="1" value="{{ old('capacity', $course->capacity) }}" required></label> {{-- celé číslo > 0; prohlížeč kontroluje, server ověří znovu --}}
    <label>Začátek <input name="starts_at" type="datetime-local" value="{{ old('starts_at', $course->starts_at?->format('Y-m-d\TH:i')) }}" required></label> {{-- datum a čas; formát, kterému rozumí pole datetime-local --}}
    <button type="submit">Uložit</button> {{-- odeslání --}}
</form>

@if ($course->exists) {{-- mazat lze jen existující kurz --}}
    <form method="post" action="{{ route('courses.destroy', $course) }}" onsubmit="return confirm('Opravdu smazat kurz i s přihláškami?')"> {{-- potvrzení před smazáním --}}
        @csrf {{-- CSRF token --}}
        @method('DELETE') {{-- metoda DELETE pro smazání --}}
        <button type="submit">Smazat kurz</button> {{-- tlačítko --}}
    </form>
@endif {{-- konec podmínky --}}
@endsection {{-- konec obsahu --}}
