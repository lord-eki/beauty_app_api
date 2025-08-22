<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessLocationRequest;
use App\Http\Requests\UpdateBusinessLocationRequest;
use App\Models\BusinessLocation;

class BusinessLocationController extends Controller
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
    public function store(StoreBusinessLocationRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(BusinessLocation $businessLocation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBusinessLocationRequest $request, BusinessLocation $businessLocation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BusinessLocation $businessLocation)
    {
        //
    }
}
