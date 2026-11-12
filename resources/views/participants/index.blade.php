@extends('layout') {{-- použijeme základní rozvržení --}}
@section('title', 'Účastníci') {{-- titulek --}}

@section('content') {{-- začátek obsahu --}}
<h1>Účastníci</h1> {{-- nadpis --}}
<p><a href="{{ route('participants.create') }}">+ Nový účastník</a></p> {{-- odkaz na formulář --}}
<table> {{-- tabulka účastníků --}}
    <tr><th>Jméno</th><th>E-mail</th><th>Přihlášek</th><th></th></tr> {{-- záhlaví --}}
    @forelse ($participants as $participant) {{-- jeden řádek za účastníka --}}
        <tr>
            <td>{{ $participant->name }}</td> {{-- jméno --}}
            <td>{{ $participant->email }}</td> {{-- e-mail --}}
            <td>{{ $participant->enrollments_count }}</td> {{-- počet přihlášek (spočítal withCount v controlleru) --}}
            <td><a href="{{ route('participants.edit', $participant) }}">upravit</a></td> {{-- odkaz na úpravu --}}
        </tr>
    @empty {{-- žádní účastníci --}}
        <tr><td colspan="4">Zatím žádní účastníci.</td></tr> {{-- informace --}}
    @endforelse {{-- konec cyklu --}}
</table>
@endsection {{-- konec obsahu --}}
