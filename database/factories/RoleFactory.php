<?php

/*
 * Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'custom_'.fake()->unique()->lexify('????????'),
            'is_system' => false,
            'is_portal' => false,
            'requires_two_factor' => false,
        ];
    }

    public function portal(): static
    {
        return $this->state(['is_portal' => true]);
    }
}
