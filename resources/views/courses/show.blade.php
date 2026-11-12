@extends('layout') {{-- použijeme základní rozvržení --}}
@section('title', $course->name) {{-- titulek = název kurzu --}}

@section('content') {{-- začátek obsahu --}}
<h1>{{ $course->name }} <small>({{ $course->code }})</small></h1> {{-- název a kód --}}
<p>Začátek: {{ $course->starts_at->format('j. n. Y H:i') }}</p> {{-- termín --}}
<p>Obsazenost: <strong>{{ $course->activeCount() }} / {{ $course->capacity }}</strong> {{-- obsazeno / kapacita --}}
    @if ($course->isFull()) {{-- kurz je plný --}}
        <span class="full">– plně obsazeno</span> {{-- upozornění --}}
    @else {{-- jsou volná místa --}}
        – volných míst: {{ $course->freeSeats() }} {{-- počet volných míst --}}
    @endif {{-- konec podmínky --}}
</p>
<p><a href="{{ route('courses.edit', $course) }}">Upravit kurz</a></p> {{-- odkaz na úpravu --}}

<h2>Přihlásit účastníka</h2> {{-- sekce přihlášení --}}
@if ($course->isFull()) {{-- plný kurz --}}
    <p class="full">Kurz je plně obsazen, další přihlášení není možné.</p> {{-- formulář se nezobrazí --}}
@elseif ($candidates->isEmpty()) {{-- není koho přihlásit --}}
    <p>Žádný další účastník k přihlášení. <a href="{{ route('participants.create') }}">Vytvořit účastníka</a></p> {{-- odkaz na nového účastníka --}}
@else {{-- je místo i kandidát --}}
    <form method="post" action="{{ route('courses.enroll', $course) }}"> {{-- formulář přihlášení --}}
        @csrf {{-- CSRF token --}}
        <select name="participant_id" required> {{-- výběr účastníka --}}
            @foreach ($candidates as $p) {{-- jedna možnost za každého kandidáta --}}
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->email }})</option> {{-- jméno a e-mail --}}
            @endforeach {{-- konec cyklu --}}
        </select>
        <button type="submit">Přihlásit</button> {{-- odeslání --}}
    </form>
@endif {{-- konec podmínky --}}

<h2>Seznam přihlášených</h2> {{-- sekce s přihláškami --}}
<table> {{-- tabulka přihlášek --}}
    <tr><th>Účastník</th><th>E-mail</th><th>Přihlášen</th><th>Stav</th></tr> {{-- záhlaví --}}
    @forelse ($enrollments as $enrollment) {{-- jeden řádek za každou přihlášku --}}
        <tr>
            <td>{{ $enrollment->participant->name }}</td> {{-- jméno účastníka --}}
            <td>{{ $enrollment->participant->email }}</td> {{-- e-mail --}}
            <td>{{ $enrollment->enrolled_at->format('j. n. Y H:i') }}</td> {{-- kdy se přihlásil --}}
            <td>
                <form class="inline" method="post" action="{{ route('enrollments.status', $enrollment) }}"> {{-- formulář změny stavu --}}
                    @csrf {{-- CSRF token --}}
                    @method('PATCH') {{-- metoda PATCH = částečná úprava --}}
                    <select name="status" onchange="this.form.submit()"> {{-- výběr stavu; změna rovnou odešle formulář --}}
                        @foreach ($statuses as $status) {{-- enrolled / cancelled / completed --}}
                            <option value="{{ $status->value }}" @selected($status === $enrollment->status)>{{ $status->label() }}</option> {{-- aktuální stav je předvybraný --}}
                        @endforeach {{-- konec cyklu --}}
                    </select>
                    <noscript><button type="submit">Uložit</button></noscript> {{-- tlačítko pro prohlížeče bez JavaScriptu --}}
                </form>
            </td>
        </tr>
    @empty {{-- žádné přihlášky --}}
        <tr><td colspan="4">Zatím nikdo není přihlášen.</td></tr> {{-- informace --}}
    @endforelse {{-- konec cyklu --}}
</table>
@endsection {{-- konec obsahu --}}
