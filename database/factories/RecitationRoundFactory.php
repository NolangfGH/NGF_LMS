<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\RecitationRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecitationRound>
 */
class RecitationRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'C++ output recitation',
            'term' => 'Term1',
            'question' => 'Which C++ object writes to standard output?',
            'accepted_answers' => ['cout', 'std::cout'],
            'case_sensitive' => true,
            'status' => 'open',
        ];
    }

    public function forClassroom(Classroom $classroom): static
    {
        return $this->state(fn (): array => ['classroom_id' => $classroom->id, 'teacher_account_id' => $classroom->teacher_account_id]);
    }
}
