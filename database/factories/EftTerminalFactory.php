<?php

namespace Database\Factories;

use App\Models\EftTerminal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EftTerminal>
 */
class EftTerminalFactory extends Factory
{
    protected $model = EftTerminal::class;

    public function definition(): array
    {
        return [
            'key' => 'terminal-' . $this->faker->unique()->numerify('####'),
            'label' => $this->faker->words(2, true) . ' Terminal',
            'pos_id' => EftTerminal::generatePosId(),
            'secret_sandbox' => null,
            'secret_live' => null,
            'is_default' => false,
        ];
    }
}
