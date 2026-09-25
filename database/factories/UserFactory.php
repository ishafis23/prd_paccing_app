<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    private ?string $roleToAssign = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08' . fake()->numerify('##########'),
            'password' => 'password',
            'status' => UserStatus::Aktif,
        ];
    }

    public function withRole(string|RoleName $role): self
    {
        $this->roleToAssign = is_string($role) ? $role : $role->value;
        return $this;
    }

    public function afterCreating(mixed $model): void
    {
        if ($this->roleToAssign) {
            $model->assignRole($this->roleToAssign);
            $model->refresh();
        }
    }
}
