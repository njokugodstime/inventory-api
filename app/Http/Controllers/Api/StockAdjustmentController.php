<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\Product;
use App\Models\StockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = StockAdjustment::with(['product', 'user']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }

        $adjustments = $query->latest()
            ->paginate($request->integer('per_page', 15));

        return StockAdjustmentResource::collection($adjustments);
    }

    public function store(StoreStockAdjustmentRequest $request)
    {
        $validated = $request->validated();

        $adjustment = DB::transaction(function () use ($validated, $request) {
            $product = Product::lockForUpdate()->findOrFail($validated['product_id']);

            if ($validated['type'] === 'decrease' && $product->quantity < $validated['quantity']) {
                abort(422, 'Cannot decrease stock below zero. Current quantity: '.$product->quantity);
            }

            $product->quantity = $validated['type'] === 'increase'
                ? $product->quantity + $validated['quantity']
                : $product->quantity - $validated['quantity'];

            $product->save();

            return StockAdjustment::create([
                ...$validated,
                'user_id' => $request->user()->id,
            ]);
        });

        $adjustment->load(['product', 'user']);

        return (new StockAdjustmentResource($adjustment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load(['product', 'user']);

        return new StockAdjustmentResource($stockAdjustment);
    }
}