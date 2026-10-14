<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDemoRequestStatus;
use App\Http\Resources\DemoRequestCollection;
use App\Http\Resources\DemoRequestResource;
use App\Services\DemoRequestService;

class DemoRequestAdminController extends Controller
{
    public function __construct(protected DemoRequestService $service) {}

    public function index()
    {
        $demoRequests = $this->service->getAll(request('per_page', 20));

        return new DemoRequestCollection($demoRequests);
    }

    public function show(string $id)
    {
        $demoRequest = $this->service->find($id);

        return new DemoRequestResource($demoRequest);
    }

    public function update(UpdateDemoRequestStatus $request, string $id)
    {
        $demoRequest = $this->service->updateStatus($id, $request->validated());

        return (new DemoRequestResource($demoRequest))
            ->additional([
                'message' => 'Demo request updated successfully.',
            ]);
    }
}
