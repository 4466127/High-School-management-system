<?php

namespace App\Services;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Section;
use App\Support\ModelChanges;
use Illuminate\Support\Facades\DB;

/** سال تحصیلی، دوره‌ها و انتقال ساختار (بخش‌ها و معلمان) به سال تازه. */
class AcademicYearService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array{name:string,start_date:string,end_date:string}  $data
     */
    public function create(array $data, ?AcademicYear $copyFrom = null, bool $copyAssignments = false): AcademicYear
    {
        return DB::transaction(function () use ($data, $copyFrom, $copyAssignments) {
            $year = AcademicYear::create($data + ['status' => 'open']);

            $copied = 0;
            if ($copyFrom) {
                $copyFrom->load('sections.assignments');
                foreach ($copyFrom->sections as $old) {
                    $new = Section::create([
                        'academic_year_id' => $year->id,
                        'grade_level_id' => $old->grade_level_id,
                        'name' => $old->name,
                        'shift' => $old->shift,
                        'class_teacher_id' => $old->class_teacher_id,
                        'room' => $old->room,
                        'capacity' => $old->capacity,
                    ]);
                    if ($copyAssignments) {
                        foreach ($old->assignments as $assignment) {
                            $new->assignments()->create(['subject_id' => $assignment->subject_id, 'staff_id' => $assignment->staff_id]);
                        }
                    }
                    $copied++;
                }
            }

            // اولین سال تحصیلی خودکار سال جاری می‌شود
            if (! AcademicYear::where('is_current', true)->exists()) {
                $this->markCurrent($year);
            }

            $this->logger->log('academic_year.created', $year, $year->name, new: [
                'name' => $year->name,
                'copied_sections' => $copied,
                'copied_assignments' => $copyFrom && $copyAssignments,
            ]);

            return $year;
        });
    }

    public function update(AcademicYear $year, array $data): void
    {
        DB::transaction(function () use ($year, $data) {
            $year->fill($data);
            [$old, $new] = ModelChanges::capture($year);
            $year->save();
            if ($new !== []) {
                $this->logger->log('academic_year.updated', $year, $year->name, $old, $new);
            }
        });
    }

    public function setCurrent(AcademicYear $year): void
    {
        DB::transaction(function () use ($year) {
            $previous = AcademicYear::where('is_current', true)->value('name');
            $this->markCurrent($year);
            $this->logger->log('academic_year.set_current', $year, $year->name, ['current' => $previous], ['current' => $year->name]);
        });
    }

    public function addTerm(AcademicYear $year, array $data): AcademicTerm
    {
        return DB::transaction(function () use ($year, $data) {
            $term = $year->terms()->create($data);
            $this->logger->log('academic_year.term_added', $year, $year->name.' — '.$term->name);

            return $term;
        });
    }

    public function updateTerm(AcademicTerm $term, array $data): void
    {
        DB::transaction(function () use ($term, $data) {
            $term->fill($data);
            [$old, $new] = ModelChanges::capture($term);
            $term->save();
            if ($new !== []) {
                $this->logger->log('academic_year.term_updated', $term->academicYear, $term->name, $old, $new);
            }
        });
    }

    public function deleteTerm(AcademicTerm $term): void
    {
        DB::transaction(function () use ($term) {
            $term->delete();
            $this->logger->log('academic_year.term_removed', $term->academicYear, $term->name);
        });
    }

    private function markCurrent(AcademicYear $year): void
    {
        AcademicYear::where('is_current', true)->where('id', '!=', $year->id)->update(['is_current' => false]);
        $year->forceFill(['is_current' => true])->save();
        AcademicYear::forgetCurrent();
    }
}
