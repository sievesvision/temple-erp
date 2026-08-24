<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display the inventory dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        // Fetch all items
        $items = Inventory::orderBy('item_name', 'asc')->get();

        // Calculate statistics
        $totalItems = $items->count();
        $outOfStock = $items->where('quantity', '<=', 0)->count();
        $lowStock = $items->filter(function ($item) {
            return $item->quantity > 0 && $item->quantity <= $item->minimum_threshold;
        })->count();

        // Get filter parameter if any
        $filter = $request->input('filter');
        if ($filter === 'low') {
            $items = $items->filter(function ($item) {
                return $item->quantity > 0 && $item->quantity <= $item->minimum_threshold;
            });
        } elseif ($filter === 'out') {
            $items = $items->where('quantity', '<=', 0);
        }

        // Fetch recent transactions
        $transactions = InventoryTransaction::with('item')
            ->orderBy('transaction_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return view('admin.manage-inventory', compact(
            'items',
            'totalItems',
            'outOfStock',
            'lowStock',
            'filter',
            'transactions'
        ));
    }

    /**
     * Store a newly created inventory item.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'item_name' => 'required|string|max:255|unique:inventories,item_name',
            'category' => 'required|string|max:100',
            'quantity' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'minimum_threshold' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $item = Inventory::create([
                'item_name' => $validated['item_name'],
                'category' => $validated['category'],
                'quantity' => $validated['quantity'],
                'unit' => $validated['unit'],
                'minimum_threshold' => $validated['minimum_threshold'],
                'last_restocked' => $validated['quantity'] > 0 ? date('Y-m-d') : null,
            ]);

            // If initial stock is greater than 0, log a transaction
            if ($item->quantity > 0) {
                InventoryTransaction::create([
                    'item_id' => $item->item_id,
                    'transaction_type' => 'Restock',
                    'quantity' => $item->quantity,
                    'remarks' => 'Initial stock on creation',
                    'transaction_date' => date('Y-m-d'),
                ]);
            }

            DB::commit();
            return redirect()->back()->with('success', 'Inventory item created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create inventory item: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Update inventory item details.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'item_name' => 'required|string|max:255|unique:inventories,item_name,' . $id . ',item_id',
            'category' => 'required|string|max:100',
            'unit' => 'required|string|max:50',
            'minimum_threshold' => 'required|numeric|min:0',
        ]);

        try {
            $item = Inventory::findOrFail($id);
            $item->update($validated);
            return redirect()->back()->with('success', 'Inventory item updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update item: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Restock or Consume inventory item.
     */
    public function adjustStock(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        $validated = $request->validate([
            'transaction_type' => 'required|string|in:Restock,Consume,Adjustment',
            'quantity' => 'required|numeric|min:0.01',
            'remarks' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $item = Inventory::findOrFail($id);
            $type = $validated['transaction_type'];
            $qty = $validated['quantity'];

            if ($type === 'Restock') {
                $item->quantity += $qty;
                $item->last_restocked = date('Y-m-d');
            } elseif ($type === 'Consume') {
                if ($item->quantity < $qty) {
                    return redirect()->back()->with('error', 'Insufficient stock. Current stock is ' . $item->quantity . ' ' . $item->unit);
                }
                $item->quantity -= $qty;
            } elseif ($type === 'Adjustment') {
                // If it is adjustment, we treat quantity as the new absolute stock value
                // and calculate the difference to log in transaction
                $diff = $qty - $item->quantity;
                $item->quantity = $qty;

                if ($diff > 0) {
                    $type = 'Restock';
                    $qty = $diff;
                } else {
                    $type = 'Consume';
                    $qty = abs($diff);
                }
            }

            $item->save();

            // Log transaction
            InventoryTransaction::create([
                'item_id' => $item->item_id,
                'transaction_type' => $type,
                'quantity' => $qty,
                'remarks' => $validated['remarks'] ?? ($validated['transaction_type'] . ' stock adjustment'),
                'transaction_date' => date('Y-m-d'),
            ]);

            DB::commit();
            return redirect()->back()->with('success', 'Stock level adjusted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to adjust stock: ' . $e->getMessage());
        }
    }

    /**
     * Delete an inventory item.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            return redirect()->back()->with('error', 'Unauthorized access.');
        }

        try {
            $item = Inventory::findOrFail($id);
            $item->delete(); // Cascading delete will handle transaction logs
            return redirect()->back()->with('success', 'Inventory item deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete inventory item: ' . $e->getMessage());
        }
    }
}
