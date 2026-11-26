<?php

namespace App\Http\Controllers\Letters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Letters\LetterTemplateRequest;
use App\Models\LetterTemplate;
use App\Services\LetterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** قالب‌های مکتوب (letters.manage). قالب حذف نمی‌شود؛ غیرفعال می‌شود. */
class LetterTemplateController extends Controller
{
    public function __construct(private readonly LetterService $letters) {}

    public function index(): View
    {
        return view('letters.templates', [
            'templates' => LetterTemplate::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'placeholders' => LetterService::PLACEHOLDERS,
        ]);
    }

    public function store(LetterTemplateRequest $request): RedirectResponse
    {
        $this->letters->saveTemplate(null, $request->validated(), $request->user());

        return redirect()->route('letters.templates.index')->with('success', __('letters.template_saved'));
    }

    public function update(LetterTemplateRequest $request, LetterTemplate $template): RedirectResponse
    {
        $this->letters->saveTemplate($template, $request->validated(), $request->user());

        return redirect()->route('letters.templates.index')->with('success', __('letters.template_saved'));
    }
}
