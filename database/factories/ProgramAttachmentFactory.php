<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\ProgramAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgramAttachment>
 */
class ProgramAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'uploaded_by' => User::factory(),
            'original_name' => 'proposal.pdf',
            'disk' => 'local',
            'path' => 'program-attachments/'.Str::uuid()->toString().'.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1024,
        ];
    }
}
