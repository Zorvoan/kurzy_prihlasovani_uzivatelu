<?php // otevírací značka PHP souboru

use App\Http\Controllers\CourseController; // controller kurzů
use App\Http\Controllers\EnrollmentController; // controller přihlášek
use App\Http\Controllers\ParticipantController; // controller účastníků
use Illuminate\Support\Facades\Route; // fasáda pro definici adres (rout)

Route::redirect('/', '/courses'); // úvodní stránka přesměruje na seznam kurzů

Route::resource('courses', CourseController::class); // 7 standardních adres CRUD: index, create, store, show, edit, update, destroy
Route::resource('participants', ParticipantController::class); // totéž pro účastníky

Route::post('courses/{course}/enrollments', [EnrollmentController::class, 'store']) // POST /courses/5/enrollments = přihlásit do kurzu 5
    ->name('courses.enroll'); // jméno routy pro route('courses.enroll', $course)
Route::patch('enrollments/{enrollment}/status', [EnrollmentController::class, 'updateStatus']) // PATCH /enrollments/3/status = změnit stav přihlášky 3
    ->name('enrollments.status'); // jméno routy
