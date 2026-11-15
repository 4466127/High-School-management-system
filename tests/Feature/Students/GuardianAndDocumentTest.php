<?php

namespace Tests\Feature\Students;

use App\Enums\Role;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GuardianAndDocumentTest extends TestCase
{
    private User $office;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->office = $this->userWithRole(Role::Office);
    }

    public function test_siblings_share_the_same_guardian_record(): void
    {
        [$brother, $sister] = Student::factory()->count(2)->create();
        $data = ['name' => 'رحیم نوری', 'relation' => 'پدر', 'phone' => '0700123456'];

        $this->actingAs($this->office)->post("/students/{$brother->id}/guardians", $data)->assertRedirect();
        $this->actingAs($this->office)->post("/students/{$sister->id}/guardians", $data)->assertRedirect();

        $this->assertSame(1, Guardian::where('phone', '0700123456')->count());
        $this->assertSame(2, Guardian::where('phone', '0700123456')->first()->students()->count());
    }

    public function test_only_one_primary_guardian_per_student(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->office)->post("/students/{$student->id}/guardians", ['name' => 'پدر', 'relation' => 'پدر']);
        $this->actingAs($this->office)->post("/students/{$student->id}/guardians", ['name' => 'کاکا', 'relation' => 'کاکا', 'is_primary' => '1']);

        $guardians = $student->guardians()->get();
        $this->assertCount(2, $guardians);
        $this->assertSame(['کاکا'], $guardians->filter(fn ($g) => $g->pivot->is_primary)->pluck('name')->values()->all());
    }

    public function test_first_guardian_becomes_primary_and_removing_primary_promotes_next(): void
    {
        $student = Student::factory()->create();
        $this->actingAs($this->office)->post("/students/{$student->id}/guardians", ['name' => 'اول', 'relation' => 'پدر']);
        $this->actingAs($this->office)->post("/students/{$student->id}/guardians", ['name' => 'دوم', 'relation' => 'مادر']);
        $first = Guardian::where('name', 'اول')->firstOrFail();

        $this->assertTrue((bool) $student->guardians()->whereKey($first->id)->first()->pivot->is_primary);

        $this->actingAs($this->office)->delete("/students/{$student->id}/guardians/{$first->id}")->assertRedirect();

        $this->assertModelMissing($first); // به هیچ دانش‌آموز دیگری وصل نبود
        $this->assertTrue((bool) $student->guardians()->first()->pivot->is_primary);
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.guardian_removed', 'subject_id' => $student->id]);
    }

    public function test_shared_guardian_is_kept_when_detached_from_one_child(): void
    {
        [$a, $b] = Student::factory()->count(2)->create();
        $guardian = Guardian::factory()->create();
        $a->guardians()->attach($guardian, ['relation' => 'پدر', 'is_primary' => true]);
        $b->guardians()->attach($guardian, ['relation' => 'پدر', 'is_primary' => true]);

        $this->actingAs($this->office)->delete("/students/{$a->id}/guardians/{$guardian->id}");

        $this->assertModelExists($guardian);
        $this->assertSame(1, $guardian->students()->count());
    }

    public function test_guardian_update_changes_info_and_relation(): void
    {
        $student = Student::factory()->create();
        $guardian = Guardian::factory()->create(['name' => 'قدیم']);
        $student->guardians()->attach($guardian, ['relation' => 'کاکا', 'is_primary' => true]);

        $this->actingAs($this->office)->put("/students/{$student->id}/guardians/{$guardian->id}", [
            'name' => 'جدید', 'relation' => 'ماما', 'phone' => '0788000111',
        ])->assertRedirect();

        $this->assertSame('جدید', $guardian->fresh()->name);
        $this->assertSame('ماما', $student->guardians()->first()->pivot->relation);
        $this->assertTrue((bool) $student->guardians()->first()->pivot->is_primary, 'Primary stays primary');
    }

    public function test_guardian_of_another_student_cannot_be_edited_through_this_student(): void
    {
        $student = Student::factory()->create();
        $other = Guardian::factory()->create();

        $this->actingAs($this->office)->put("/students/{$student->id}/guardians/{$other->id}", ['name' => 'x', 'relation' => 'x'])->assertNotFound();
        $this->actingAs($this->office)->delete("/students/{$student->id}/guardians/{$other->id}")->assertNotFound();
    }

    public function test_document_upload_download_and_delete(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->office)->post("/students/{$student->id}/documents", [
            'title' => 'کاپی تذکره',
            'file' => UploadedFile::fake()->create('tazkira.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $document = $student->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('tazkira.pdf', $document->original_name);
        $this->assertSame($this->office->id, $document->uploaded_by);

        $this->actingAs($this->office)->get("/students/{$student->id}/documents/{$document->id}")
            ->assertOk()
            ->assertDownload('tazkira.pdf');

        // معلم اسناد را نمی‌تواند ببیند
        $this->actingAs($this->userWithRole(Role::Teacher))->get("/students/{$student->id}/documents/{$document->id}")->assertForbidden();

        $this->actingAs($this->office)->delete("/students/{$student->id}/documents/{$document->id}")->assertRedirect();
        $this->assertModelMissing($document);
        Storage::disk('local')->assertMissing($document->file_path);
        $this->assertDatabaseHas('activity_logs', ['action' => 'student.document_removed', 'subject_id' => $student->id]);
    }

    public function test_document_type_and_size_are_validated(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->office)->post("/students/{$student->id}/documents", [
            'title' => 'x',
            'file' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream'),
        ])->assertSessionHasErrors('file');

        $this->actingAs($this->office)->post("/students/{$student->id}/documents", [
            'title' => 'x',
            'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('file');
    }

    public function test_document_of_another_student_is_not_reachable(): void
    {
        [$a, $b] = Student::factory()->count(2)->create();
        $this->actingAs($this->office)->post("/students/{$a->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]);
        $document = $a->documents()->firstOrFail();

        $this->actingAs($this->office)->get("/students/{$b->id}/documents/{$document->id}")->assertNotFound();
        $this->actingAs($this->office)->delete("/students/{$b->id}/documents/{$document->id}")->assertNotFound();
        $this->assertModelExists($document);
    }
}
