<?php

namespace App\Http\Controllers\Api;

use App\Models\Obat;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class OrderControllerUser extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Perbaikan nama relasi: items.obats, users, confirmationBy
        $orders = Order::with(['items.obats', 'users', 'confirmationBy'])
            ->where('users_id', $request->user()->users_id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($order) {
                return $this->formatOrder($order);
            });

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.obats_id' => 'required|exists:obats,obats_id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|max:50',
            'shipping_address' => 'nullable|string', // Jika tidak diisi, pakai alamat user
            'notes'          => 'nullable|string',
            'image_payment'  => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $user = $request->user();

        // Gunakan alamat user jika tidak diisi
        $shippingAddress = $request->shipping_address ?? $user->address;

        if (empty($shippingAddress)) {
            return response()->json([
                'success' => false,
                'message' => 'Alamat pengiriman harus diisi. Silakan update alamat di profil atau isi shipping_address.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $totalPrice = 0;
            $orderItems = [];

            // Validasi stok dan hitung total
            foreach ($request->items as $item) {
                $obat = Obat::find($item['obats_id']);

                if (!$obat) {
                    throw new \Exception("Obat dengan ID {$item['obats_id']} tidak ditemukan.");
                }

                if ($obat->stock < $item['quantity']) {
                    throw new \Exception("Stok {$obat->name} tidak mencukupi. Tersedia: {$obat->stock}");
                }

                $subtotal = $obat->price * $item['quantity'];
                $totalPrice += $subtotal;

                $orderItems[] = [
                    'obats_id'   => $obat->obats_id,
                    'quantity'   => $item['quantity'],
                    'unit_price' => $obat->price,
                    'subtotal'   => $subtotal,
                ];

                // Kurangi stok
                $obat->decrement('stock', $item['quantity']);
            }

            // Buat order
            $order = Order::create([
                'users_id'          => $user->users_id,
                'total_price'      => $totalPrice,
                'shipping_address' => $shippingAddress,
                'payment_method'   => $request->payment_method,
                'payment_status'   => 'pending',
                'notes'            => $request->notes,
            ]);

            if ($request->hasFile('image_payment')) {
                // Simpan gambar ke storage/app/public/payments dan dapatkan path-nya
                $path = $request->file('image_payment')->store('images', 'public');
                // Update order yang sudah dibuat dengan path gambar
                $order->image_payment = $path;
                $order->save();
            }

            // Buat order items
            foreach ($orderItems as $item) {
                $order->items()->create($item);
            }

            DB::commit();

            // Load relasi untuk response
            $order->load(['items.obats', 'users']);

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil dibuat',
                'data'    => $this->formatOrder($order)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function formatOrder($order)
    {
        return [
            'orders_id'        => $order->orders_id,
            // Relasi di model Order adalah users()
            'user'             => $order->users ? [
                'users_id' => $order->users->users_id,
                'name'     => $order->users->name,
                'email'    => $order->users->email,
            ] : null,
            'total_price'      => $order->total_price,
            'shipping_address' => $order->shipping_address,
            'payment_method'   => $order->payment_method,
            'payment_status'   => $order->payment_status,
            // Kolom di database adalah image_payment
            'image_payment_url' => $order->image_payment
                ? url('/api/image/' . $order->image_payment)
                : null,
            'paid_at'          => $order->paid_at,
            'confirmed_by'     => $order->confirmationBy ? [
                'users_id' => $order->confirmationBy->users_id,
                'name'     => $order->confirmationBy->name,
            ] : null,
            'notes'            => $order->notes,
            'items'            => $order->items->map(function ($item) {
                return [
                    'order_items_id' => $item->order_items_id,
                    // Relasi di model Order_Items adalah obats()
                    'obat'           => $item->obats ? [
                        'obats_id' => $item->obats->obats_id,
                        'name'     => $item->obats->name,
                        'image'    => $item->obats->image
                            ? url('/api/image/' . $item->obats->image)
                            : null,
                    ] : null,
                    'quantity'       => $item->quantity,
                    'unit_price'     => $item->unit_price,
                    'subtotal'       => $item->subtotal,
                ];
            }),
            'created_at'       => $order->created_at,
            'updated_at'       => $order->updated_at,
        ];
    }
}
