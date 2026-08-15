<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_no'  => 'M-'.$this->faker->unique()->numerify('#####'),
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'gender'     => $this->faker->randomElement(['male', 'female']),
            'phone'      => $this->faker->phoneNumber(),
            'status'     => 'active',
            'joined_on'  => now()->subMonths(rand(1, 24))->toDateString(),
        ];
    }
}
