<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /api/admin/delivery/settings: the warehouse and the whole list of distance tiers.
 */
class UpdateDeliverySettingsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'warehouse'           => 'required|array',
            'warehouse.name'      => 'required|string|max:255',
            'warehouse.address'   => 'required|string|max:255',
            'warehouse.latitude'  => 'required|numeric|between:-90,90',
            'warehouse.longitude' => 'required|numeric|between:-180,180',
            'tiers'               => 'required|array|min:1',
            'tiers.*.min_km'      => 'required|numeric|min:0',
            'tiers.*.max_km'      => 'required|numeric',
            'tiers.*.fee'         => 'required|integer|min:0',
        ];
    }

    /**
     * Each tier must be a real range, and ranges must not overlap.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $previous = null;
                foreach ($this->tiers() as $tier) {
                    if ($tier['max_km'] <= $tier['min_km']) {
                        $validator->errors()->add('tiers', "Each tier's max_km must be greater than its min_km ({$tier['min_km']} to {$tier['max_km']} km).");

                        return;
                    }

                    if ($previous && $tier['min_km'] < $previous['max_km']) {
                        $validator->errors()->add('tiers', "Tiers overlap: {$previous['min_km']} to {$previous['max_km']} km and {$tier['min_km']} to {$tier['max_km']} km.");

                        return;
                    }

                    $previous = $tier;
                }
            },
        ];
    }

    /**
     * The tiers as numbers, sorted by min_km.
     *
     * @return array<int, array{min_km: float, max_km: float, fee: int}>
     */
    public function tiers(): array
    {
        return collect($this->input('tiers', []))
            ->map(fn ($tier) => [
                'min_km' => (float) $tier['min_km'],
                'max_km' => (float) $tier['max_km'],
                'fee'    => (int) $tier['fee'],
            ])
            ->sortBy('min_km')
            ->values()
            ->all();
    }
}
