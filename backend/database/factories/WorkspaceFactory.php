<?php
namespace Database\Factories;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;
    public function definition(): array { return ['name' => fake()->company()]; }
}
