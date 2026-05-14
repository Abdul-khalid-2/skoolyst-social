<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        $name = fake()->company() . "'s Workspace";

        return [
            'owner_id' => User::factory(),
            'name'     => $name,
            'slug'     => Str::slug($name) . '-' . fake()->unique()->numerify('####'),
            'plan'     => 'free',
            'is_active' => true,
        ];
    }
}
