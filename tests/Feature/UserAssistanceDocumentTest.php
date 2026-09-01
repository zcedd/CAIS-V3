<?php

use App\Models\Assistance;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Program;
use App\Models\User;
use App\Support\DocumentTypeSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * @return array{department: Department, user: User, program: Program, assistance: Assistance, documentType: DocumentType}
 */
function createAssistanceDocumentContext(): array
{
    $department = Department::create(['name' => 'Department A']);
    $user = User::factory()->create(['department_id' => $department->id]);
    $program = Program::create([
        'name' => 'Relief Program',
        'descriptions' => 'Details',
        'start_at' => now()->toDateString(),
        'end_at' => null,
        'department_id' => $department->id,
        'is_closed' => false,
        'is_organization' => false,
    ]);
    $assistance = Assistance::create([
        'program_id' => $program->id,
        'date_requested' => now()->toDateString(),
        'user_id' => $user->id,
    ]);
    $documentType = DocumentType::query()->where('slug', DocumentTypeSlug::ValidId)->firstOrFail();

    return compact('department', 'user', 'program', 'assistance', 'documentType');
}

test('authenticated users can upload a document to an assistance in their department', function () {
    Storage::fake('local');
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createAssistanceDocumentContext();

    $file = UploadedFile::fake()->image('national-id.jpg', 100, 100);

    $this->actingAs($user)
        ->post(route('user.assistances.documents.store', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => $file,
            'notes' => 'Front of National ID',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $document = $assistance->documents()->first();

    expect($document)->not->toBeNull()
        ->and($document->document_type_id)->toBe($documentType->id)
        ->and($document->original_name)->toBe('national-id.jpg')
        ->and($document->notes)->toBe('Front of National ID')
        ->and($document->uploaded_by)->toBe($user->id);

    Storage::disk('local')->assertExists($document->path);
});

test('assistance profile includes uploaded documents and checklist', function () {
    Storage::fake('local');
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createAssistanceDocumentContext();

    $program->documentRequirements()->create([
        'document_type_id' => $documentType->id,
        'is_required' => true,
        'required_before' => 'verified',
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('user.assistances.documents.store', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->create('id.pdf', 120, 'application/pdf'),
        ]);

    $this->actingAs($user)
        ->get(route('user.assistances.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/assistances/show')
            ->where('documents.checklist.0.document_type_id', $documentType->id)
            ->where('documents.checklist.0.is_complete', true)
            ->where('documents.checklist.0.documents.0.original_name', 'id.pdf')
            ->where('documents.missing_for_verified', [])
            ->has('document_types'));
});

test('authenticated users can download an assistance document', function () {
    Storage::fake('local');
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createAssistanceDocumentContext();

    $this->actingAs($user)
        ->post(route('user.assistances.documents.store', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->create('id.pdf', 80, 'application/pdf'),
        ]);

    $document = $assistance->documents()->firstOrFail();

    $this->actingAs($user)
        ->get(route('user.assistances.documents.show', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
            'document' => $document->id,
        ]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('authenticated users can delete an assistance document', function () {
    Storage::fake('local');
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createAssistanceDocumentContext();

    $this->actingAs($user)
        ->post(route('user.assistances.documents.store', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->image('photo.png'),
        ]);

    $document = $assistance->documents()->firstOrFail();
    $path = $document->path;

    $this->actingAs($user)
        ->delete(route('user.assistances.documents.destroy', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
            'document' => $document->id,
        ]))
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertModelMissing($document);
    Storage::disk('local')->assertMissing($path);
});

test('users cannot upload documents for another department', function () {
    Storage::fake('local');
    [
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createAssistanceDocumentContext();

    $otherDepartment = Department::create(['name' => 'Department B']);
    $otherUser = User::factory()->create(['department_id' => $otherDepartment->id]);

    $this->actingAs($otherUser)
        ->post(route('user.assistances.documents.store', [
            'department' => $otherDepartment->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->image('id.jpg'),
        ])
        ->assertForbidden();
});

test('document uploads reject disallowed file types', function () {
    Storage::fake('local');
    [
        'department' => $department,
        'user' => $user,
        'program' => $program,
        'assistance' => $assistance,
        'documentType' => $documentType,
    ] = createAssistanceDocumentContext();

    $this->actingAs($user)
        ->post(route('user.assistances.documents.store', [
            'department' => $department->slug,
            'program' => $program->id,
            'assistance' => $assistance->id,
        ]), [
            'document_type_id' => $documentType->id,
            'file' => UploadedFile::fake()->create('notes.exe', 20, 'application/x-msdownload'),
        ])
        ->assertSessionHasErrors('file');
});
