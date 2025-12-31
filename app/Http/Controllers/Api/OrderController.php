<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Obat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // List orders for current user
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items.obat')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // ========== CASHIER ENDPOINTS ==========

    /**
     * List all orders (cashier only)
     * Supports filters: ?status=pending|completed|cancelled&payment_status=pending|paid
     */
    public function allOrders(Request $request)
    {
        $user = $request->user();

        if (! $user->isCashier()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Order::with(['user:id,name,email', 'items.obat', 'paidBy:id,name'])
            ->orderBy('created_at', 'desc');

        // Filter by order status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by user_id (optional)
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Pagination (default 20 per page)
        $perPage = $request->input('per_page', 20);
        $orders = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders->items(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ]
        ]);
    }

    /**
     * List orders with pending payments (cashier only)
     * Quick access to orders that need payment confirmation
     */
    public function pendingPayments(Request $request)
    {
        $user = $request->user();

        if (! $user->isCashier()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $orders = Order::with(['user:id,name,email', 'items.obat'])
            ->where('payment_status', 'pending')
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'asc') // oldest first (FIFO)
            ->get();

        return response()->json([
            'success' => true,
            'count' => $orders->count(),
            'data' => $orders
        ]);
    }

    /**
     * Get order detail (cashier can view any order)
     */
    public function showForCashier(Request $request, $id)
    {
        $user = $request->user();

        if (! $user->isCashier()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order = Order::with(['user:id,name,email,address', 'items.obat', 'paidBy:id,name'])
            ->find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    // ========== END CASHIER ENDPOINTS ==========

    // Store new order
    public function store(Request $request)
    {
        $request->validate([
            'obat_id'  => 'sometimes|exists:obats,id',
            'quantity' => 'sometimes|integer|min:1',
            'items' => 'sometimes|array|min:1',
            'items.*.obat_id' => 'required_with:items|exists:obats,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'shipping_address' => 'nullable|string',
            'payment_method' => 'nullable|string',
            'payment_details' => 'nullable|array',
        ]);

        $user = $request->user();

        // determine items: prefer `items` array, else single obat_id+quantity
        $items = [];
        if ($request->has('items')) {
            $items = $request->items;
        } else {
            if ($request->filled('obat_id') && $request->filled('quantity')) {
                $items = [[ 'obat_id' => $request->obat_id, 'quantity' => (int)$request->quantity ]];
            } else {
                return response()->json(['success' => false, 'message' => 'items atau obat_id+quantity diperlukan'], 422);
            }
        }

        // Transaction to avoid race conditions on stock
        try {
            $order = DB::transaction(function () use ($user, $items, $request) {
                $total = 0;
                $orderItemsData = [];

                // lock and process each item
                foreach ($items as $it) {
                    $obat = Obat::lockForUpdate()->find($it['obat_id']);
                    if (! $obat) {
                        throw new \Exception('Obat tidak ditemukan');
                    }
                    $qty = (int) $it['quantity'];
                    if ($obat->stock < $qty) {
                        throw new \Exception('Stok tidak mencukupi');
                    }
                    $obat->stock -= $qty;
                    $obat->save();

                    $subtotal = $obat->price * $qty;
                    $total += $subtotal;

                    $orderItemsData[] = [
                        'obat_id' => $obat->id,
                        'quantity' => $qty,
                        'unit_price' => $obat->price,
                        'subtotal' => $subtotal,
                    ];
                }

                // fallback shipping address: request -> user profile
                $shipping = $request->shipping_address ?? $user->address;
                if (! $shipping) {
                    throw new \Exception('Shipping address required');
                }

                $paymentMethod = $request->payment_method;

                // Determine payment status based on user role and payment method
                if ($user->isCashier()) {
                    $paymentStatus = 'paid';
                } else {
                    if ($paymentMethod === 'cash') {
                        $paymentStatus = 'paid';
                    } else {
                        $paymentStatus = 'pending';
                    }
                }

                // create order
                $order = Order::create([
                    'user_id' => $user->id,
                    'total_price' => $total,
                    'status' => 'pending',
                    'shipping_address' => $shipping,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'payment_details' => $request->payment_details ?? null,
                ]);

                // create order_items
                foreach ($orderItemsData as $oid) {
                    $order->items()->create($oid);
                }

                return $order->load('items.obat');
            });
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($msg === 'Stok tidak mencukupi' || $msg === 'Obat tidak ditemukan') {
                return response()->json(['success' => false, 'message' => $msg], 400);
            }
            if ($msg === 'Shipping address required') {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $msg], 500);
        }

        return response()->json(['success' => true, 'message' => 'Order berhasil dibuat', 'data' => $order], 201);
    }

    // Show a single order (must belong to user)
    public function show(Request $request, $id)
    {
    $order = Order::with('items.obat')->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['success' => true, 'data' => $order]);
    }

    // Cancel an order: only pending orders can be cancelled; restore stock
    public function cancel(Request $request, $id)
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json(['message' => 'Hanya order pending yang dapat dibatalkan'], 400);
        }

        DB::transaction(function () use ($order) {
            // If order has items (multi-item), restore each item's stock
            if ($order->relationLoaded('items') === false) {
                $order->load('items');
            }

            if ($order->items && $order->items->count() > 0) {
                foreach ($order->items as $item) {
                    $obat = Obat::lockForUpdate()->find($item->obat_id);
                    if ($obat) {
                        $obat->stock += $item->quantity;
                        $obat->save();
                    }
                }
            } else {
                // legacy fallback: orders may have obat_id/quantity columns
                try {
                    $obat = Obat::lockForUpdate()->find($order->obat_id);
                    if ($obat) {
                        $qty = $order->quantity ?? 0;
                        $obat->stock += $qty;
                        $obat->save();
                    }
                } catch (\Exception $e) {
                    // ignore missing legacy fields
                }
            }

            $order->status = 'cancelled';
            $order->save();
        });

        return response()->json(['success' => true, 'message' => 'Order dibatalkan', 'data' => $order]);
    }

    // Confirm payment (only cashier)
    public function confirmPayment(Request $request, $id)
    {
        $user = $request->user();

        if (! $user->isCashier()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->payment_status === Order::PAYMENT_STATUS_PAID) {
            return response()->json(['message' => 'Order already paid'], 400);
        }

        $order->payment_status = Order::PAYMENT_STATUS_PAID;
        $order->paid_at = now();
        $order->paid_by = $user->id;
        $order->save();

        return response()->json(['success' => true, 'message' => 'Payment confirmed', 'data' => $order]);
    }

    // Notify payment: owner (patient) or cashier can upload proof or send payment metadata
    public function notifyPayment(Request $request, $id)
    {
        $order = Order::find($id);

        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $user = $request->user();

        if ($order->user_id !== $user->id && ! $user->isCashier()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'payment_method' => 'nullable|string',
            'payment_details' => 'nullable|array',
            'payment_proof' => 'nullable|file|image|max:4096',
        ]);

        $details = $order->payment_details ?? [];

        // Accept payment_details as array, object or JSON string
        $incoming = $request->input('payment_details');
        if ($incoming) {
            // if string, try to decode JSON
            if (is_string($incoming)) {
                $decoded = json_decode($incoming, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $incoming = $decoded;
                } else {
                    $incoming = null;
                }
            }

            // if object (stdClass), convert to array
            if (is_object($incoming)) {
                $incoming = json_decode(json_encode($incoming), true);
            }

            if (is_array($incoming)) {
                $details = array_merge($details, $incoming);
            }
        }

        // file upload handling: accept specific key or any uploaded file
        if ($request->hasFile('payment_proof')) {
            $file = $request->file('payment_proof');
            if ($file->isValid()) {
                $path = $file->store('payments', 'public');
                $details['proof_url'] = url('/api/image/' . $path);
                $details['proof_path'] = $path;
            }
        } else {
            // try any uploaded files (covers keys like 'proof_url' or nested keys)
            $allFiles = $request->allFiles();
            if (!empty($allFiles)) {
                foreach ($allFiles as $f) {
                    // $f might be array (multiple files) or UploadedFile
                    if (is_array($f)) {
                        $f = reset($f);
                    }
                    if ($f && $f->isValid()) {
                        $path = $f->store('payments', 'public');
                        $details['proof_url'] = url('/api/image/' . $path);
                        $details['proof_path'] = $path;
                        break;
                    }
                }
            }
        }

        if ($request->filled('payment_method')) {
            $order->payment_method = $request->payment_method;
        }

        $order->payment_details = $details;
        $order->save();

        return response()->json(['success' => true, 'message' => 'Payment info saved', 'data' => $order]);
    }
}
