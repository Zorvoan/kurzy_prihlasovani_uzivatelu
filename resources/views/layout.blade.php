{{-- Základní rozvržení stránky; ostatní šablony ho používají přes @extends('layout') --}}
<!doctype html> {{-- typ dokumentu HTML5 --}}
<html lang="cs"> {{-- jazyk stránky je čeština --}}
<head>
    <meta charset="utf-8"> {{-- kódování znaků kvůli diakritice --}}
    <meta name="viewport" content="width=device-width, initial-scale=1"> {{-- správné zobrazení na mobilu --}}
    <title>@yield('title', 'Kurzy')</title> {{-- titulek v záložce; stránka ho může nastavit přes @section('title') --}}
    <style>
        body { font-family: system-ui, sans-serif; max-width: 960px; margin: 0 auto; padding: 16px; } /* základní vzhled */
        nav a { margin-right: 12px; }                     /* mezery mezi odkazy v menu */
        table { border-collapse: collapse; width: 100%; } /* tabulka přes celou šířku */
        th, td { border-bottom: 1px solid #ddd; padding: 6px; text-align: left; } /* oddělení řádků */
        .flash { padding: 8px; background: #e6f4ea; margin: 8px 0; }  /* zelená zpráva o úspěchu */
        .errors { background: #fdecea; padding: 8px 8px 8px 28px; }   /* červený seznam chyb */
        .full { color: #b00020; font-weight: bold; }      /* zvýraznění plného kurzu */
        form.inline { display: inline; }                  /* malé formuláře na jednom řádku */
        label { display: block; margin: 8px 0; }          /* popisky formuláře pod sebou */
    </style>
</head>
<body>
    <nav> {{-- hlavní menu --}}
        <a href="{{ route('courses.index') }}">Kurzy</a> {{-- odkaz na seznam kurzů --}}
        <a href="{{ route('participants.index') }}">Účastníci</a> {{-- odkaz na seznam účastníků --}}
    </nav>

    @if (session('success')) {{-- zpráva o úspěchu uložená přes ->with('success', ...) --}}
        <div class="flash">{{ session('success') }}</div> {{-- zobrazíme ji --}}
    @endif {{-- konec podmínky --}}

    @if ($errors->any()) {{-- $errors obsahuje chyby validace (Laravel ho plní automaticky) --}}
        <ul class="errors"> {{-- červený seznam --}}
            @foreach ($errors->all() as $error) {{-- projdeme všechny chyby --}}
                <li>{{ $error }}</li> {{-- jedna chyba = jedna položka; {{ }} automaticky escapuje HTML (ochrana před XSS) --}}
            @endforeach {{-- konec cyklu --}}
        </ul>
    @endif {{-- konec podmínky --}}

    @yield('content') {{-- sem se vloží obsah konkrétní stránky --}}
</body>
</html>
