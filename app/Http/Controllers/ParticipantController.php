<?php // otevírací značka PHP souboru

namespace App\Http\Controllers; // jmenný prostor controllerů

use App\Http\Requests\ParticipantRequest; // validace formuláře účastníka
use App\Models\Participant; // model účastníka
use Illuminate\Http\RedirectResponse; // typ odpovědi "přesměrování"
use Illuminate\View\View; // typ odpovědi "šablona"

// Controller pro CRUD účastníků.
class ParticipantController extends Controller // dědíme ze základního controlleru
{
    public function index(): View // seznam účastníků
    {
        $participants = Participant::withCount('enrollments')->orderBy('name')->get(); // účastníci + počet jejich přihlášek
        return view('participants.index', compact('participants')); // vykreslíme šablonu
    } // konec index()

    public function create(): View // formulář pro nového účastníka
    {
        return view('participants.form', ['participant' => new Participant]); // prázdný účastník
    } // konec create()

    public function store(ParticipantRequest $request): RedirectResponse // uložení nového účastníka
    {
        Participant::create($request->validated()); // uložíme zvalidovaná data
        return redirect()->route('participants.index')->with('success', 'Účastník byl vytvořen.'); // zpět na seznam
    } // konec store()

    public function show(Participant $participant): RedirectResponse // detail účastníka nepotřebujeme...
    {
        return redirect()->route('participants.edit', $participant); // ...přesměrujeme na formulář úprav
    } // konec show()

    public function edit(Participant $participant): View // formulář úprav
    {
        return view('participants.form', compact('participant')); // předvyplněný formulář
    } // konec edit()

    public function update(ParticipantRequest $request, Participant $participant): RedirectResponse // uložení úprav
    {
        $participant->update($request->validated()); // uložíme zvalidovaná data
        return redirect()->route('participants.index')->with('success', 'Účastník byl upraven.'); // zpět na seznam
    } // konec update()

    public function destroy(Participant $participant): RedirectResponse // smazání účastníka
    {
        $participant->delete(); // smaže účastníka; jeho přihlášky smaže databáze (cascadeOnDelete)
        return redirect()->route('participants.index')->with('success', 'Účastník byl smazán.'); // zpět na seznam
    } // konec destroy()
} // konec třídy
