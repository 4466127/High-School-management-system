<?php

namespace App\Http\Controllers\Finance;

use App\Enums\FeePeriod;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeRate;
use App\Models\FeeType;
use App\Models\GradeLevel;
use App\Rules\ValidAmount;
use App\Services\ActivityLogger;
use App\Services\FinanceService;
use App\Support\Digits;
use App\Support\ModelChanges;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** انواع فیس و نرخ هر صنف در سال جاری (مجوز: finance.manage؛ فیس روشن). */
class FeeSetupController extends Controller
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function index(): View
    {
        $year = AcademicYear::current();
        $rates = [];
        if ($year) {
            FeeRate::where('academic_year_id', $year->id)->get()->each(function (FeeRate $r) use (&$rates) {
                $rates[$r->fee_type_id][$r->grade_level_id] = Money::plain($r->amount);
            });
        }

        return view('finance.setup.index', [
            'year' => $year,
            'types' => FeeType::orderBy('sort_order')->orderBy('name')->get(),
            'grades' => GradeLevel::where('is_active', true)->orderBy('sort_order')->get(),
            'rates' => $rates,
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $data = $this->validateType($request);
        $type = FeeType::create($data);
        $this->logger->log('fee_type.created', $type, $type->name, new: ['period' => $type->period->label()]);

        return redirect()->route('finance.setup.index')->with('success', __('finance.type_saved', ['name' => $type->name]));
    }

    public function updateType(Request $request, FeeType $feeType): RedirectResponse
    {
        $feeType->fill($this->validateType($request, $feeType));
        [$old, $new] = ModelChanges::capture($feeType);
        $feeType->save();
        if ($new !== []) {
            $this->logger->log('fee_type.updated', $feeType, $feeType->name, $old, $new);
        }

        return redirect()->route('finance.setup.index')->with('success', __('finance.type_saved', ['name' => $feeType->name]));
    }

    /** rates[fee_type_id][grade_level_id] = مبلغ (خالی = نرخ ندارد) */
    public function saveRates(Request $request): RedirectResponse
    {
        $year = AcademicYear::current();
        abort_unless($year, 404);
        $request->validate([
            'rates' => ['array'],
            'rates.*' => ['array'],
            'rates.*.*' => ['nullable', new ValidAmount(allowZero: true)],
        ], [], ['rates.*.*' => __('finance.rate')]);

        $typeIds = FeeType::pluck('id')->all();
        $gradeIds = GradeLevel::pluck('id')->all();

        $changed = DB::transaction(function () use ($request, $year, $typeIds, $gradeIds) {
            $changed = [];
            foreach ((array) $request->input('rates', []) as $typeId => $grades) {
                if (! in_array((int) $typeId, $typeIds, true)) {
                    continue;
                }
                foreach ((array) $grades as $gradeId => $value) {
                    if (! in_array((int) $gradeId, $gradeIds, true)) {
                        continue;
                    }
                    $cents = FinanceService::parseAmount((string) $value);
                    $rate = FeeRate::where(['fee_type_id' => $typeId, 'academic_year_id' => $year->id, 'grade_level_id' => $gradeId])->first();
                    $before = $rate ? Money::toCents($rate->amount) : null;

                    if ($cents === null || $cents === 0) {
                        if ($rate) {
                            $rate->delete();
                            $changed[] = "{$typeId}/{$gradeId}: ".Money::plain($rate->amount).' → —';
                        }

                        continue;
                    }
                    if ($before !== $cents) {
                        FeeRate::updateOrCreate(
                            ['fee_type_id' => $typeId, 'academic_year_id' => $year->id, 'grade_level_id' => $gradeId],
                            ['amount' => Money::fromCents($cents)],
                        );
                        $changed[] = "{$typeId}/{$gradeId}: ".($before === null ? '—' : Money::plain(Money::fromCents($before))).' → '.Money::plain(Money::fromCents($cents));
                    }
                }
            }
            if ($changed !== []) {
                $this->logger->log('fee_rate.updated', null, __('finance.rates_title').' — '.$year->name, new: ['count' => count($changed), 'changes' => implode('؛ ', array_slice($changed, 0, 40))]);
            }

            return $changed;
        });

        return redirect()->route('finance.setup.index')->with('success', __('finance.rates_saved', ['count' => Digits::toPersian((string) count($changed))]));
    }

    private function validateType(Request $request, ?FeeType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('fee_types', 'name')->ignore($type)],
            'period' => ['required', Rule::enum(FeePeriod::class)],
            'sort_order' => ['required', 'integer', 'min:1', 'max:99'],
            'is_active' => ['nullable', 'boolean'],
        ], [], __('finance.attributes'));
        $data['is_active'] = $request->boolean('is_active', $type === null);

        return $data;
    }
}
