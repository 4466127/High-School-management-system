<?php

namespace Tests\Feature;

use App\Http\Requests\Concerns\ConvertsJalaliDates;
use App\Rules\ValidJalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class JalaliDateInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->post('/_test/jalali', fn (SampleJalaliRequest $request) => response()->json($request->validated()));
    }

    public function test_form_request_converts_jalali_input_to_gregorian(): void
    {
        $this->postJson('/_test/jalali', ['birth_date' => '۱۴۰۵/۰۸/۲۴', 'note' => 'x'])
            ->assertOk()
            ->assertExactJson(['birth_date' => '2026-11-15', 'note' => 'x']);
    }

    public function test_invalid_jalali_date_gets_persian_error(): void
    {
        $this->postJson('/_test/jalali', ['birth_date' => '1404/12/30'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.birth_date.0', 'تاریخ تولد باید تاریخ شمسی معتبر باشد، مثلاً ۱۴۰۵/۰۸/۲۴.');
    }

    public function test_component_shows_stored_date_in_jalali(): void
    {
        $html = Blade::render('<x-jalali-date name="birth_date" label="تاریخ تولد" :value="$v" required />', ['v' => '2026-11-15']);

        $this->assertStringContainsString('value="۱۴۰۵/۰۸/۲۴"', $html);
        $this->assertStringContainsString('data-jdate', $html);
        $this->assertStringContainsString('تاریخ تولد', $html);
    }
}

class SampleJalaliRequest extends FormRequest
{
    use ConvertsJalaliDates;

    protected function jalaliDateFields(): array
    {
        return ['birth_date'];
    }

    public function rules(): array
    {
        return [
            'birth_date' => ['required', new ValidJalaliDate],
            'note' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['birth_date' => 'تاریخ تولد'];
    }
}
