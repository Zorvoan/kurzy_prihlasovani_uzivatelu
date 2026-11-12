<?php // otevírací značka PHP souboru

namespace App\Services; // jmenný prostor služeb (business logika mimo controllery)

use App\Enums\EnrollmentStatus; // výčet stavů
use App\Models\Course; // model kurzu
use App\Models\Enrollment; // model přihlášky
use App\Models\Participant; // model účastníka
use Illuminate\Support\Facades\DB; // fasáda databáze (transakce)
use Illuminate\Validation\ValidationException; // výjimka, kterou Laravel sám zobrazí jako chybu formuláře

// Služba obsahuje pravidla přihlašování: zákaz duplicit a hlídání kapacity.
class EnrollmentService // obyčejná třída, Laravel ji umí sám vytvořit a předat do controlleru
{
    public function enroll(Course $course, Participant $participant): Enrollment // přihlásí účastníka do kurzu
    {
        return DB::transaction(function () use ($course, $participant) { // vše v jedné transakci – uloží se všechno, nebo nic
            $course = Course::whereKey($course->id)->lockForUpdate()->firstOrFail(); // znovu načteme kurz a zamkneme ho (MySQL/PostgreSQL), aby dva lidé nezabrali poslední místo současně

            $existing = Enrollment::where('course_id', $course->id) // hledáme už existující přihlášku...
                ->where('participant_id', $participant->id) // ...téhož účastníka do téhož kurzu
                ->first(); // první nalezenou (může být nejvýš jedna díky unique indexu)

            if ($existing && $existing->status !== EnrollmentStatus::Cancelled) { // přihláška existuje a není zrušená
                throw ValidationException::withMessages([ // vyhodíme chybu validace...
                    'enrollment' => 'Účastník je do tohoto kurzu už přihlášen.', // ...se zprávou pro uživatele
                ]); // účastník se nesmí přihlásit dvakrát
            } // konec podmínky

            $this->ensureFreeSeat($course); // hlídání kapacity – je ještě volné místo?

            if ($existing) { // účastník se dříve odhlásil (cancelled) -> přihlášku obnovíme
                $existing->update([ // upravíme původní řádek (unique index nedovolí druhý)
                    'status' => EnrollmentStatus::Enrolled, // stav zpět na enrolled
                    'enrolled_at' => now(), // nový čas přihlášení
                ]); // konec update
                return $existing; // vrátíme obnovenou přihlášku
            } // konec podmínky

            return $course->enrollments()->create([ // jinak vytvoříme novou přihlášku (course_id doplní Laravel)
                'participant_id' => $participant->id, // kdo se přihlašuje
                'enrolled_at' => now(), // kdy se přihlásil
                'status' => EnrollmentStatus::Enrolled, // výchozí stav enrolled
            ]); // konec create
        }); // konec transakce
    } // konec enroll()

    public function changeStatus(Enrollment $enrollment, EnrollmentStatus $status): Enrollment // změní stav přihlášky
    {
        return DB::transaction(function () use ($enrollment, $status) { // jedna transakce
            $course = Course::whereKey($enrollment->course_id)->lockForUpdate()->firstOrFail(); // zamkneme kurz kvůli kontrole kapacity
            $enrollment->refresh(); // načteme aktuální stav přihlášky z databáze

            if ($status->isActive() && ! $enrollment->status->isActive()) { // přechod z cancelled do enrolled/completed obsadí místo
                $this->ensureFreeSeat($course); // proto musíme zkontrolovat kapacitu
            } // konec podmínky

            $enrollment->update(['status' => $status]); // uložíme nový stav
            return $enrollment; // vrátíme upravenou přihlášku
        }); // konec transakce
    } // konec changeStatus()

    private function ensureFreeSeat(Course $course): void // vyhodí chybu, když je kurz plný
    {
        $active = $course->activeEnrollments()->count(); // kolik míst je obsazeno
        if ($active >= $course->capacity) { // obsazeno je už tolik, kolik je kapacita
            throw ValidationException::withMessages([ // chyba validace
                'enrollment' => "Kurz „{$course->name}“ je plně obsazen ({$course->capacity} míst).", // zpráva pro uživatele
            ]); // konec výjimky
        } // konec podmínky
    } // konec ensureFreeSeat()
} // konec třídy
