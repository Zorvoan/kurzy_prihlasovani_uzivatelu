<?php // otevírací značka PHP souboru

namespace App\Http\Requests; // jmenný prostor pro validační požadavky

use Illuminate\Foundation\Http\FormRequest; // základní třída pro validaci
use Illuminate\Validation\Rule; // pomocník pro pravidlo unique

// Validace formuláře účastníka.
class ParticipantRequest extends FormRequest // dědíme z FormRequest
{
    public function authorize(): bool // smí uživatel požadavek poslat?
    {
        return true; // ano, aplikace nemá přihlašování
    } // konec authorize()

    protected function prepareForValidation(): void // úprava dat před validací
    {
        $this->merge([ // přepíšeme hodnoty
            'name' => trim((string) $this->input('name')), // jméno bez mezer na krajích
            'email' => mb_strtolower(trim((string) $this->input('email'))), // e-mail bez mezer a malými písmeny -> "Jan@X.cz" a "jan@x.cz" jsou stejný e-mail
        ]); // konec merge
    } // konec prepareForValidation()

    public function rules(): array // validační pravidla
    {
        return [ // pole pravidel
            'name' => ['required', 'string', 'max:255'], // jméno je povinné
            'email' => [ // pravidla pro e-mail
                'required', 'string', 'max:255', // povinný text
                'email:rfc,strict', // platný e-mail podle normy RFC (odmítne např. "a@b" nebo "a b@c.cz")
                'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', // navíc vyžadujeme doménu s tečkou, např. "@example.com"
                Rule::unique('participants', 'email')->ignore($this->route('participant')?->id), // jedinečný (při úpravě ignoruje sám sebe)
            ], // konec pravidel e-mailu
        ]; // konec pole pravidel
    } // konec rules()

    public function messages(): array // české chybové zprávy
    {
        return [ // pole zpráv
            'name.required' => 'Jméno účastníka je povinné.', // chybí jméno
            'email.required' => 'Zadejte platný e-mail.', // chybí e-mail
            'email.email' => 'Zadejte platný e-mail.', // neplatný formát
            'email.regex' => 'Zadejte platný e-mail.', // chybí doména s tečkou
            'email.unique' => 'Účastník s tímto e-mailem už existuje.', // e-mail je obsazený
        ]; // konec pole zpráv
    } // konec messages()
} // konec třídy
