<?php // otevírací značka PHP souboru

namespace App\Http\Controllers; // jmenný prostor controllerů

use App\Enums\EnrollmentStatus; // výčet stavů
use App\Models\Course; // model kurzu
use App\Models\Enrollment; // model přihlášky
use App\Models\Participant; // model účastníka
use App\Services\EnrollmentService; // služba s pravidly přihlašování
use Illuminate\Http\RedirectResponse; // typ odpovědi "přesměrování"
use Illuminate\Http\Request; // HTTP požadavek
use Illuminate\Validation\Rule; // pomocník pro pravidlo "enum"

// Controller pro přihlášení do kurzu a změnu stavu přihlášky.
class EnrollmentController extends Controller // dědíme ze základního controlleru
{
    public function __construct(private EnrollmentService $service) {} // Laravel sám vytvoří a předá službu (dependency injection)

    public function store(Request $request, Course $course): RedirectResponse // přihlášení účastníka do kurzu
    {
        $data = $request->validate([ // validace formuláře
            'participant_id' => ['required', 'integer', 'exists:participants,id'], // účastník musí být vybrán a existovat
        ], [ // české zprávy
            'participant_id.required' => 'Vyberte účastníka.', // nic nevybráno
            'participant_id.exists' => 'Vybraný účastník neexistuje.', // neplatné ID
        ]); // konec validace

        $this->service->enroll($course, Participant::findOrFail($data['participant_id'])); // přihlášení – při plném kurzu/duplicitě vyhodí ValidationException a Laravel vrátí uživatele zpět s chybou
        return back()->with('success', 'Účastník byl přihlášen.'); // zpět na detail kurzu se zprávou
    } // konec store()

    public function updateStatus(Request $request, Enrollment $enrollment): RedirectResponse // změna stavu přihlášky
    {
        $data = $request->validate([ // validace
            'status' => ['required', Rule::enum(EnrollmentStatus::class)], // stav musí být jeden z enrolled/cancelled/completed
        ], [ // české zprávy
            'status.required' => 'Vyberte stav.', // nic nevybráno
            'status.enum' => 'Neplatný stav přihlášky.', // neznámý stav
        ]); // konec validace

        $this->service->changeStatus($enrollment, EnrollmentStatus::from($data['status'])); // změna stavu (s kontrolou kapacity)
        return redirect()->route('courses.show', $enrollment->course_id)->with('success', 'Stav přihlášky byl změněn.'); // zpět na detail kurzu
    } // konec updateStatus()
} // konec třídy
