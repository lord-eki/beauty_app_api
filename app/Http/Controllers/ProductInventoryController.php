<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductInventoryRequest;
use App\Http\Requests\UpdateProductInventoryRequest;
use App\Models\ProductInventory;

class ProductInventoryController extends Controller
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
    public function store(StoreProductInventoryRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ProductInventory $productInventory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductInventoryRequest $request, ProductInventory $productInventory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ProductInventory $productInventory)
    {
        //
    }
}
