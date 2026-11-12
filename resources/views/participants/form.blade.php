@extends('layout') {{-- použijeme základní rozvržení --}}
@section('title', $participant->exists ? 'Upravit účastníka' : 'Nový účastník') {{-- titulek --}}

@section('content') {{-- začátek obsahu --}}
<h1>{{ $participant->exists ? 'Upravit účastníka' : 'Nový účastník' }}</h1> {{-- nadpis --}}

<form method="post" action="{{ $participant->exists ? route('participants.update', $participant) : route('participants.store') }}"> {{-- úprava -> update, nový -> store --}}
    @csrf {{-- CSRF token --}}
    @if ($participant->exists) @method('PUT') @endif {{-- PUT pro úpravu --}}
    <label>Jméno <input name="name" value="{{ old('name', $participant->name) }}" required></label> {{-- jméno --}}
    <label>E-mail <input name="email" type="email" value="{{ old('email', $participant->email) }}" required></label> {{-- e-mail (platný, jedinečný) --}}
    <button type="submit">Uložit</button> {{-- odeslání --}}
</form>

@if ($participant->exists) {{-- mazat lze jen existujícího účastníka --}}
    <form method="post" action="{{ route('participants.destroy', $participant) }}" onsubmit="return confirm('Opravdu smazat účastníka i jeho přihlášky?')"> {{-- potvrzení --}}
        @csrf {{-- CSRF token --}}
        @method('DELETE') {{-- metoda DELETE --}}
        <button type="submit">Smazat účastníka</button> {{-- tlačítko --}}
    </form>
@endif {{-- konec podmínky --}}
@endsection {{-- konec obsahu --}}
