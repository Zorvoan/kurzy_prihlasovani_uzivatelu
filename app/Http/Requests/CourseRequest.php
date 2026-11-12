<?php // otevírací značka PHP souboru

namespace App\Http\Requests; // jmenný prostor pro validační požadavky

use App\Models\Course; // model kurzu
use Closure; // typ pro vlastní validační pravidlo (funkce)
use Illuminate\Foundation\Http\FormRequest; // základní třída – Laravel zvaliduje data ještě před controllerem
use Illuminate\Validation\Rule; // pomocník pro složitější pravidla (unique s výjimkou)

// Validace formuláře kurzu (vytvoření i úprava).
class CourseRequest extends FormRequest // dědíme z FormRequest
{
    public function authorize(): bool // smí uživatel tento požadavek poslat?
    {
        return true; // aplikace nemá přihlašování uživatelů, takže ano
    } // konec authorize()

    protected function prepareForValidation(): void // úprava dat PŘED validací
    {
        $this->merge([ // přepíšeme hodnoty v požadavku
            'name' => trim((string) $this->input('name')), // odstraníme mezery na krajích názvu
            'code' => trim((string) $this->input('code')), // odstraníme mezery na krajích kódu
        ]); // konec merge
    } // konec prepareForValidation()

    public function rules(): array // validační pravidla
    {
        $course = $this->route('course'); // při úpravě je v URL existující kurz, při vytváření null

        return [ // pole pravidel: název pole => seznam pravidel
            'name' => ['required', 'string', 'max:255'], // název je povinný text, max. 255 znaků
            'code' => [ // pravidla pro kód kurzu
                'required', 'string', 'max:50', // povinný text, max. 50 znaků
                Rule::unique('courses', 'code')->ignore($course?->id), // jedinečný v tabulce courses (při úpravě ignoruje sám sebe)
            ], // konec pravidel kódu
            'capacity' => [ // pravidla pro kapacitu
                'required', 'integer', 'min:1', // povinné celé číslo větší než 0
                function (string $attribute, mixed $value, Closure $fail) use ($course) { // vlastní pravidlo pro úpravu kurzu
                    if ($course instanceof Course && (int) $value < $course->activeCount()) { // nová kapacita je menší než počet přihlášených
                        $fail("Kapacitu nelze snížit pod počet aktivních přihlášek ({$course->activeCount()})."); // chybová zpráva
                    } // konec podmínky
                }, // konec vlastního pravidla
            ], // konec pravidel kapacity
            'starts_at' => ['required', 'date'], // termín je povinný a musí být platné datum
        ]; // konec pole pravidel
    } // konec rules()

    public function messages(): array // vlastní české chybové zprávy
    {
        return [ // pole: "pole.pravidlo" => zpráva
            'name.required' => 'Název kurzu je povinný.', // chybí název
            'code.required' => 'Kód kurzu je povinný.', // chybí kód
            'code.unique' => 'Kurz s tímto kódem už existuje.', // kód je obsazený
            'capacity.required' => 'Kapacita musí být celé číslo větší než 0.', // chybí kapacita
            'capacity.integer' => 'Kapacita musí být celé číslo větší než 0.', // kapacita není celé číslo
            'capacity.min' => 'Kapacita musí být celé číslo větší než 0.', // kapacita je 0 nebo záporná
            'starts_at.required' => 'Termín začátku je povinný.', // chybí termín
            'starts_at.date' => 'Termín začátku musí být platné datum a čas.', // neplatné datum
        ]; // konec pole zpráv
    } // konec messages()
} // konec třídy
