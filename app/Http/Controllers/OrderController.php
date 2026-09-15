<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Supplier;
use App\Models\Medicine;
use App\Models\OrderItem;
use App\Models\AccumulatedSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with(['supplier', 'user'])
                       ->latest()
                       ->paginate(20);
        
        return view('orders.index', compact('orders'));
    }

    public function create(Request $request)
    {
        $suppliers = Supplier::where('is_active', true)->get();
        $selectedSupplier = $request->supplier_id ? Supplier::find($request->supplier_id) : null;
        
        $accumulatedSales = [];
        if ($selectedSupplier) {
            $accumulatedSales = AccumulatedSale::where('supplier_id', $selectedSupplier->id)
                                               ->where('is_cleared', false)
                                               ->with('medicine')
                                               ->get();
        }
        
        return view('orders.create', compact('suppliers', 'selectedSupplier', 'accumulatedSales'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.pack_type' => 'required|in:pack,loose',
            'delivery_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $supplier = Supplier::findOrFail($request->supplier_id);
            
            $order = Order::create([
                'order_number' => 'ORD-' . Str::upper(Str::random(8)) . '-' . Carbon::now()->format('Ymd'),
                'supplier_id' => $request->supplier_id,
                'user_id' => auth()->id(),
                'order_date' => Carbon::today(),
                'delivery_date' => $request->delivery_date,
                'notes' => $request->notes,
                'status' => 'draft',
                'total_amount' => 0,
            ]);

            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $medicine = Medicine::findOrFail($itemData['medicine_id']);
                $unitPrice = $medicine->cost ?? 0;
                $totalPrice = $unitPrice * $itemData['quantity'];
                
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'medicine_id' => $itemData['medicine_id'],
                    'quantity' => $itemData['quantity'],
                    'pack_type' => $itemData['pack_type'],
                    'pack_size' => $medicine->pack_size,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
                
                $totalAmount += $totalPrice;
            }
            
            $order->update(['total_amount' => $totalAmount]);
            
            // Clear accumulated sales for this supplier
            AccumulatedSale::where('supplier_id', $request->supplier_id)
                           ->where('is_cleared', false)
                           ->update([
                               'is_cleared' => true,
                               'cleared_at' => Carbon::now(),
                               'cleared_by_order_id' => $order->id,
                           ]);

            DB::commit();
            
            return redirect()->route('orders.show', $order)
                             ->with('success', 'Order created successfully.');
                             
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Order creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create order: ' . $e->getMessage());
        }
    }

    public function show(Order $order)
    {
        $order->load(['supplier', 'user', 'items.medicine', 'medicines']);
        return view('orders.show', compact('order'));
    }

    public function edit(Order $order)
    {
        if (!$order->canBeModified()) {
            return redirect()->route('orders.show', $order)
                             ->with('error', 'This order cannot be modified.');
        }
        
        $order->load('items.medicine');
        $suppliers = Supplier::where('is_active', true)->get();
        $medicines = Medicine::whereHas('suppliers', function($query) use ($order) {
            $query->where('supplier_id', $order->supplier_id);
        })->get();
        
        return view('orders.edit', compact('order', 'suppliers', 'medicines'));
    }

    public function update(Request $request, Order $order)
    {
        if (!$order->canBeModified()) {
            return redirect()->route('orders.show', $order)
                             ->with('error', 'This order cannot be modified.');
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.medicine_id' => 'required|exists:medicines,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.pack_type' => 'required|in:pack,loose',
            'delivery_date' => 'nullable|date|after:today',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $order->update([
                'delivery_date' => $request->delivery_date,
                'notes' => $request->notes,
            ]);

            // Delete existing items
            $order->items()->delete();

            $totalAmount = 0;
            foreach ($request->items as $itemData) {
                $medicine = Medicine::findOrFail($itemData['medicine_id']);
                $unitPrice = $medicine->cost ?? 0;
                $totalPrice = $unitPrice * $itemData['quantity'];
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'medicine_id' => $itemData['medicine_id'],
                    'quantity' => $itemData['quantity'],
                    'pack_type' => $itemData['pack_type'],
                    'pack_size' => $medicine->pack_size,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);
                
                $totalAmount += $totalPrice;
            }
            
            $order->update(['total_amount' => $totalAmount]);

            DB::commit();
            
            return redirect()->route('orders.show', $order)
                             ->with('success', 'Order updated successfully.');
                             
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Failed to update order: ' . $e->getMessage());
        }
    }

    public function submit(Order $order)
    {
        if (!$order->canBeModified()) {
            return redirect()->route('orders.show', $order)
                             ->with('error', 'This order cannot be submitted.');
        }

        try {
            $order->update(['status' => 'sent']);
            
            // Update stock levels
            foreach ($order->items as $item) {
                $medicine = $item->medicine;
                if ($medicine->pack_type === 'pack' && $item->pack_type === 'pack') {
                    $medicine->current_stock -= ($item->quantity * ($medicine->pack_size ?? 1));
                } else {
                    $medicine->current_stock -= $item->quantity;
                }
                $medicine->save();
            }
            
            return redirect()->route('orders.index')
                             ->with('success', 'Order submitted successfully.');
                             
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to submit order: ' . $e->getMessage());
        }
    }

    public function destroy(Order $order)
    {
        if (!$order->isDraft()) {
            return redirect()->route('orders.show', $order)
                             ->with('error', 'Only draft orders can be deleted.');
        }
        
        try {
            $order->delete();
            return redirect()->route('orders.index')
                             ->with('success', 'Order deleted successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete order: ' . $e->getMessage());
        }
    }

    public function generateForSupplier(Supplier $supplier)
    {
        // Generate order from accumulated sales
        $accumulatedSales = AccumulatedSale::where('supplier_id', $supplier->id)
                                           ->where('is_cleared', false)
                                           ->with('medicine')
                                           ->get();
        
        if ($accumulatedSales->isEmpty()) {
            return redirect()->route('orders.index')
                             ->with('info', 'No accumulated sales found for this supplier.');
        }
        
        return view('orders.generate', compact('supplier', 'accumulatedSales'));
    }
}