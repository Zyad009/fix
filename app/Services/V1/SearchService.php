<?php

namespace App\Services\V1;

use App\Models\Customer;
use App\Models\Vehicle;
use App\Support\PhoneNormalizer;
use App\Support\PlateNormalizer;
use Illuminate\Database\Eloquent\Collection;

/**
 * The cashier's most used screen: one box finds a vehicle by plate or a
 * customer by phone/name.
 */
class SearchService
{
    private const LIMIT = 10;

    /**
     * @return array{vehicles: Collection<int, Vehicle>, customers: Collection<int, Customer>}
     */
    public function search(string $term): array
    {
        $plate = PlateNormalizer::normalize($term);
        $phone = PhoneNormalizer::normalize($term);

        $vehicles = $plate === ''
            ? new Collection
            : Vehicle::query()
                ->with(['customer:id,name,phone', 'carMake', 'carModel'])
                ->where('plate_normalized', 'like', "%{$plate}%")
                ->orderByRaw('plate_normalized = ? desc', [$plate])
                ->limit(self::LIMIT)
                ->get();

        $customers = Customer::query()
            ->withCount('vehicles')
            ->where(function ($query) use ($term, $phone): void {
                $query->where('name', 'like', '%'.trim($term).'%');

                if (strlen($phone) >= 3) {
                    $query->orWhere('phone', 'like', "%{$phone}%");
                }
            })
            ->limit(self::LIMIT)
            ->get();

        return ['vehicles' => $vehicles, 'customers' => $customers];
    }
}
