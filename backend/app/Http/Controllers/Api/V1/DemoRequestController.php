<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDemoRequest;
use App\Http\Resources\DemoRequestResource;
use App\Services\DemoRequestService;

class DemoRequestController extends Controller
{
    public function __construct(protected DemoRequestService $service) {}

    public function store(StoreDemoRequest $request)
    {
        $demoRequest = $this->service->store($request->validated());

        return (new DemoRequestResource($demoRequest))
            ->additional([
                'message' => 'Demo request submitted successfully. We will contact you soon.',
            ]);
    }
}
