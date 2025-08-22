<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceAvailabilityRequest;
use App\Http\Requests\UpdateServiceAvailabilityRequest;
use App\Models\ServiceAvailability;

class ServiceAvailabilityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreServiceAvailabilityRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ServiceAvailability $serviceAvailability)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateServiceAvailabilityRequest $request, ServiceAvailability $serviceAvailability)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServiceAvailability $serviceAvailability)
    {
        //
    }
}
