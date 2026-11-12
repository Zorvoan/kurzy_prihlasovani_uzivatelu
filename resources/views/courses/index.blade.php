@extends('layout') {{-- použijeme základní rozvržení --}}
@section('title', 'Kurzy') {{-- titulek stránky --}}

@section('content') {{-- začátek obsahu --}}
<h1>Kurzy</h1> {{-- nadpis --}}
<p><a href="{{ route('courses.create') }}">+ Nový kurz</a></p> {{-- odkaz na formulář nového kurzu --}}

<form method="get" action="{{ route('courses.index') }}"> {{-- filtr; GET = hodnoty jsou vidět v URL --}}
    Termín od <input type="date" name="date_from" value="{{ $filters['date_from'] }}"> {{-- kurz začíná nejdříve v tento den --}}
    do <input type="date" name="date_to" value="{{ $filters['date_to'] }}"> {{-- kurz začíná nejpozději v tento den --}}
    <input type="checkbox" name="only_free" value="1" id="only_free" @checked($filters['only_free'])> {{-- zaškrtávátko; @checked přidá atribut checked, když je podmínka pravdivá --}}
    <label for="only_free" style="display:inline">jen s volnými místy</label> {{-- popisek zaškrtávátka --}}
    <button type="submit">Filtrovat</button> {{-- odeslání filtru --}}
    <a href="{{ route('courses.index') }}">zrušit filtr</a> {{-- odkaz bez parametrů = bez filtru --}}
</form>

<table> {{-- tabulka kurzů --}}
    <tr><th>Kód</th><th>Název</th><th>Začátek</th><th>Obsazenost</th><th>Volná místa</th><th></th></tr> {{-- záhlaví --}}
    @forelse ($courses as $course) {{-- jeden řádek za každý kurz; @forelse má větev @empty pro prázdný seznam --}}
        <tr>
            <td>{{ $course->code }}</td> {{-- kód --}}
            <td><a href="{{ route('courses.show', $course) }}">{{ $course->name }}</a></td> {{-- název s odkazem na detail --}}
            <td>{{ $course->starts_at->format('j. n. Y H:i') }}</td> {{-- termín v českém formátu --}}
            <td>{{ $course->activeCount() }} / {{ $course->capacity }}</td> {{-- obsazeno / kapacita --}}
            <td @class(['full' => $course->isFull()])>{{ $course->freeSeats() }}</td> {{-- volná místa; plný kurz červeně --}}
            <td><a href="{{ route('courses.edit', $course) }}">upravit</a></td> {{-- odkaz na úpravu --}}
        </tr>
    @empty {{-- seznam je prázdný --}}
        <tr><td colspan="6">Žádné kurzy neodpovídají filtru.</td></tr> {{-- informace --}}
    @endforelse {{-- konec cyklu --}}
</table>
@endsection {{-- konec obsahu --}}
