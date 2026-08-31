<?php

namespace App\Http\Resources\Team;

use App\Http\Resources\TeamMemberResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TeamMemberCollection extends ResourceCollection
{
    public $collects = TeamMemberResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->currentPage(),
                'per_page' => $this->perPage(),
                'total' => $this->total(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem(),
            ],
        ];
    }

    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
