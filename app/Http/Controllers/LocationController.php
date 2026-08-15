<?php

namespace App\Http\Controllers;

use App\Models\Cell;
use App\Models\District;
use App\Models\Province;
use App\Models\Sector;
use App\Models\Village;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json(
            Province::orderBy('name')->get(['code', 'name'])
        );
    }

    public function districts(string $provinceCode): JsonResponse
    {
        return response()->json(
            District::where('province_code', $provinceCode)
                ->orderBy('name')
                ->get(['code', 'name'])
        );
    }

    public function sectors(string $districtCode): JsonResponse
    {
        return response()->json(
            Sector::where('district_code', $districtCode)
                ->orderBy('name')
                ->get(['code', 'name'])
        );
    }

    public function cells(string $sectorCode): JsonResponse
    {
        return response()->json(
            Cell::where('sector_code', $sectorCode)
                ->orderBy('name')
                ->get(['code', 'name'])
        );
    }

    public function villages(string $cellCode): JsonResponse
    {
        return response()->json(
            Village::where('cell_code', $cellCode)
                ->orderBy('name')
                ->get(['code', 'name'])
        );
    }
}
