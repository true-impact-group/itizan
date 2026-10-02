<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Collection;

class PackageComparison
{
    /**
     * How much the longest package saves compared with buying the shorter one repeatedly
     * for the same period (e.g. one annual package vs two six-month packages).
     *
     * @param  Collection<int, Package>  $packages
     * @return array{package: Package, halalas: int, percent: int}|null
     */
    public function longestPackageSavings(Collection $packages): ?array
    {
        $longest = $packages->sortByDesc('duration_months')->first();
        $shortest = $packages->sortBy('duration_months')->first();

        if ($longest === null || $shortest === null || $longest->is($shortest) || $longest->duration_months % $shortest->duration_months !== 0) {
            return null;
        }

        $repeats = intdiv($longest->duration_months, $shortest->duration_months);
        $regular = $shortest->price_halalas * $repeats;
        $saved = $regular - $longest->price_halalas;

        if ($saved <= 0) {
            return null;
        }

        return [
            'package' => $longest,
            'halalas' => $saved,
            'percent' => (int) round($saved / $regular * 100),
        ];
    }
}
