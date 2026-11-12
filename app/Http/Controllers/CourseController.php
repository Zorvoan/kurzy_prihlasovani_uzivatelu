<?php // otevírací značka PHP souboru

namespace App\Http\Controllers; // jmenný prostor controllerů

use App\Enums\EnrollmentStatus; // výčet stavů přihlášky
use App\Http\Requests\CourseRequest; // validace formuláře kurzu
use App\Models\Course; // model kurzu
use App\Models\Participant; // model účastníka
use Illuminate\Http\RedirectResponse; // typ odpovědi "přesměrování"
use Illuminate\Http\Request; // HTTP požadavek
use Illuminate\View\View; // typ odpovědi "šablona"

// Controller pro CRUD kurzů (Create, Read, Update, Delete).
class CourseController extends Controller // dědíme ze základního controlleru
{
    public function index(Request $request): View // seznam kurzů s filtrem
    {
        $filters = [ // načteme hodnoty filtru z URL (?date_from=...&date_to=...&only_free=1)
            'date_from' => $request->query('date_from'), // termín od (YYYY-MM-DD)
            'date_to' => $request->query('date_to'), // termín do (YYYY-MM-DD)
            'only_free' => $request->boolean('only_free'), // zaškrtnuto "jen s volnými místy"?
        ]; // konec pole filtrů

        $courses = Course::query() // začneme stavět dotaz na kurzy
            ->withOccupancy() // přidáme počet obsazených míst (active_count)
            ->when($filters['date_from'], fn ($q, $from) => $q->whereDate('starts_at', '>=', $from)) // když je vyplněno "od", kurz musí začínat nejdříve tento den
            ->when($filters['date_to'], fn ($q, $to) => $q->whereDate('starts_at', '<=', $to)) // když je vyplněno "do", kurz musí začínat nejpozději tento den
            ->when($filters['only_free'], fn ($q) => $q->withFreeSeats()) // když je zaškrtnuto, jen kurzy s volným místem
            ->orderBy('starts_at') // seřadíme podle termínu
            ->orderBy('name') // pak podle názvu
            ->get(); // provedeme dotaz

        return view('courses.index', compact('courses', 'filters')); // vykreslíme šablonu resources/views/courses/index.blade.php
    } // konec index()

    public function create(): View // formulář pro nový kurz
    {
        return view('courses.form', ['course' => new Course]); // prázdný (neuložený) kurz pro formulář
    } // konec create()

    public function store(CourseRequest $request): RedirectResponse // uložení nového kurzu
    {
        $course = Course::create($request->validated()); // validace proběhla v CourseRequest; uložíme jen zvalidovaná data
        return redirect()->route('courses.show', $course)->with('success', 'Kurz byl vytvořen.'); // přesměrujeme na detail se zprávou
    } // konec store()

    public function show(Course $course): View // detail kurzu: obsazenost + seznam přihlášených
    {
        $course->loadCount(['activeEnrollments as active_count']); // spočítáme obsazená místa
        $enrollments = $course->enrollments()->with('participant')->orderBy('enrolled_at')->get(); // přihlášky i s účastníky (jedním dotazem navíc, ne N dotazy)
        $alreadyIn = $enrollments // ID účastníků, kteří už jsou přihlášeni (a nejsou zrušení)
            ->reject(fn ($e) => $e->status === EnrollmentStatus::Cancelled) // vyřadíme zrušené přihlášky
            ->pluck('participant_id'); // vezmeme jen ID účastníků
        $candidates = Participant::whereNotIn('id', $alreadyIn)->orderBy('name')->get(); // účastníci, které lze ještě přihlásit

        return view('courses.show', [ // vykreslíme detail
            'course' => $course, // kurz
            'enrollments' => $enrollments, // přihlášky
            'candidates' => $candidates, // kandidáti pro výběr
            'statuses' => EnrollmentStatus::cases(), // všechny stavy pro výběr ve formuláři
        ]); // konec dat šablony
    } // konec show()

    public function edit(Course $course): View // formulář úpravy kurzu
    {
        return view('courses.form', compact('course')); // stejný formulář, předvyplněný
    } // konec edit()

    public function update(CourseRequest $request, Course $course): RedirectResponse // uložení úprav
    {
        $course->update($request->validated()); // uložíme zvalidovaná data
        return redirect()->route('courses.show', $course)->with('success', 'Kurz byl upraven.'); // zpět na detail
    } // konec update()

    public function destroy(Course $course): RedirectResponse // smazání kurzu
    {
        $course->delete(); // smaže kurz; přihlášky smaže databáze (cascadeOnDelete)
        return redirect()->route('courses.index')->with('success', 'Kurz byl smazán.'); // zpět na seznam
    } // konec destroy()
} // konec třídy
