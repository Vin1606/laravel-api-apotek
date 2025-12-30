<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Models\Obat;
use Illuminate\Http\Request;

class ObatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $obats = Obat::all()->map(function ($obat) {
            return [
                'id' => $obat->id,
                'name' => $obat->name,
                'description' => $obat->description,
                'price' => $obat->price,
                'stock' => $obat->stock,

                // 👇 INI YANG MEMAKAI ROUTE /image
                'image' => $obat->image
                    ? url('/api/image/' . $obat->image)
                    : null,
            ];
        });

        return response()->json([
            'data' => $obats
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validasi data yang dikirim oleh user/aplikasi
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'required|integer',
            'stock'       => 'required|integer',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $input = $request->all();

        if ($request->hasFile('image')) {
            // Simpan gambar ke storage/app/public/images dan dapatkan path-nya
            $path = $request->file('image')->store('images', 'public');
            $input['image'] = $path;
        }

        // 2. Simpan data ke database menggunakan Model Obat
        $obat = Obat::create($input);

        // 3. Kembalikan response JSON sukses
        return response()->json([
            'success' => true,
            'message' => 'Data obat berhasil ditambahkan',
            'data'    => $obat
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Obat $obat)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail Data Obat',
            'data'    => $obat
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Obat $obat)
    {
        // Validasi (gunakan 'sometimes' agar field tidak wajib diisi semua jika hanya edit sebagian)
        $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'sometimes|required|integer',
            'stock'       => 'sometimes|required|integer',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $input = $request->all();

        if ($request->hasFile('image')) {
            // Hapus gambar lama jika ada
            if ($obat->image) {
                Storage::disk('public')->delete($obat->image);
            }
            // Simpan gambar baru
            $path = $request->file('image')->store('images', 'public');
            $input['image'] = $path;
        } else {
            unset($input['image']);
        }

        // Update data
        $obat->update($input);

        return response()->json([
            'success' => true,
            'message' => 'Data obat berhasil diubah',
            'data'    => $obat
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Obat $obat)
    {
        // Hapus gambar dari storage jika ada
        if ($obat->image) {
            Storage::disk('public')->delete($obat->image);
        }

        $obat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data obat berhasil dihapus',
        ]);
    }
}
