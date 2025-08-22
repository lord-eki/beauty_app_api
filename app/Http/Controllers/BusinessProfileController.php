<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessProfileRequest;
use App\Http\Requests\UpdateBusinessProfileRequest;
use App\Models\BusinessProfile;

class BusinessProfileController extends Controller
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
    public function store(StoreBusinessProfileRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(BusinessProfile $businessProfile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBusinessProfileRequest $request, BusinessProfile $businessProfile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BusinessProfile $businessProfile)
    {
        //
    }
}
