<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use LogsActivity;

    // Get all customers
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->city) {
            $query->where('city', $request->city);
        }

        if ($request->province) {
            $query->where('province', $request->province);
        }

        if ($request->specialization) {
            $query->where('specialization', $request->specialization);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('contact_no', 'like', "%{$request->search}%")
                  ->orWhere('city', 'like', "%{$request->search}%")
                  ->orWhere('barangay', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->latest()->paginate(10);

        return response()->json([
            'customers'  => CustomerResource::collection($customers),
            'pagination' => [
                'total'        => $customers->total(),
                'per_page'     => $customers->perPage(),
                'current_page' => $customers->currentPage(),
                'last_page'    => $customers->lastPage(),
                'from'         => $customers->firstItem(),
                'to'           => $customers->lastItem(),
            ]
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

        $this->logActivity(
            action      : 'CREATE',
            module      : 'Customers',
            description : "Created customer: {$customer->name}",
            newData     : $customer->toArray()
        );

        return response()->json([
            'message'  => 'Customer created successfully',
            'customer' => new CustomerResource($customer)
        ], 201);
    }

    // Update customer
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $oldData = $customer->toArray();
        $customer->update($request->validated());

        $this->logActivity(
            action      : 'UPDATE',
            module      : 'Customers',
            description : "Updated customer: {$customer->name}",
            oldData     : $oldData,
            newData     : $customer->fresh()->toArray()
        );

        return response()->json([
            'message'  => 'Customer updated successfully',
            'customer' => new CustomerResource($customer)
        ], 200);
    }

    // Delete customer
    public function destroy(Customer $customer)
    {
        $this->logActivity(
            action      : 'DELETE',
            module      : 'Customers',
            description : "Deleted customer: {$customer->name}",
            oldData     : $customer->toArray()
        );

        $customer->delete();

        return response()->json([
            'message' => 'Customer deleted successfully'
        ], 200);
    }
}