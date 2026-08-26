<?php

namespace Database\Factories;

use App\Models\Assistance;
use App\Models\AssistanceDocument;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistanceDocument>
 */
class AssistanceDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = Str::uuid()->toString().'.pdf';

        return [
            'assistance_id' => Assistance::query()->value('id') ?? Assistance::create([
                'program_id' => Program::query()->value('id') ?? Program::create([
                    'name' => fake()->words(2, true),
                    'descriptions' => fake()->sentence(),
                    'start_at' => now()->toDateString(),
                    'end_at' => null,
                    'department_id' => Department::query()->value('id')
                        ?? Department::create(['name' => fake()->company()])->id,
                    'is_closed' => false,
                    'is_organization' => false,
                ])->id,
                'date_requested' => now()->toDateString(),
                'user_id' => User::query()->value('id') ?? User::factory()->create()->id,
            ])->id,
            'document_type_id' => DocumentType::query()->value('id')
                ?? DocumentType::factory()->create()->id,
            'uploaded_by' => User::query()->value('id') ?? User::factory()->create()->id,
            'original_name' => 'id-card.pdf',
            'disk' => 'local',
            'path' => 'assistance-documents/'.$filename,
            'mime_type' => 'application/pdf',
            'size' => 1024,
            'notes' => null,
        ];
    }
}
