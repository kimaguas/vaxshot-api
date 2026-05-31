<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    // Get all customers
    public function index(Request $request)
    {
        $query = Customer::query();

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by city
        if ($request->city) {
            $query->where('city', $request->city);
        }

        // Filter by province
        if ($request->province) {
            $query->where('province', $request->province);
        }

        // Filter by specialization
        if ($request->specialization) {
            $query->where('specialization', $request->specialization);
        }

        // Search by name or contact
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('contact_no', 'like', "%{$request->search}%")
                  ->orWhere('city', 'like', "%{$request->search}%")
                  ->orWhere('barangay', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->latest()->get();

        return response()->json([
            'customers' => CustomerResource::collection($customers)
        ], 200);
    }

    // Get single customer
    public function show(Customer $customer)
    {
        return response()->json([
            'customer' => new CustomerResource($customer)
        ], 200);
    }

    // Create customer
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());

        return response()->json([
            'message'  => 'Customer created successfully',
            'customer' => new CustomerResource($customer)
        ], 201);
    }

    // Update customer
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());

        return response()->json([
            'message'  => 'Customer updated successfully',
            'customer' => new CustomerResource($customer)
        ], 200);
    }

    // Delete customer
    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully'
        ], 200);
    }
}