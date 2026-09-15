<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tahap 2.5 D'mentai — form SIMPLE untuk Master Bahan Baku & Kemasan
 * (tanpa foto/resep/varian, beda dari ProdukJualRequest).
 */
class BahanBakuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role?->value, ['owner', 'admin_pusat', 'admin_gudang', 'manajer_cabang']);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'harga_beli_terakhir' => preg_replace('/\D/', '', $this->harga_beli_terakhir ?? ''),
        ]);
    }

    public function rules(): array
    {
        $itemId = $this->route('bahanBaku')?->id;

        return [
            'kode_item'           => ['required', 'string', 'max:30', Rule::unique('items', 'kode_item')->ignore($itemId)],
            'nama_item'           => ['required', 'string', 'max:150'],
            'item_category_id'    => ['nullable', 'exists:item_categories,id'],
            'tipe'                => ['required', Rule::in(['bahan_baku', 'kemasan', 'tambahan_gratis'])],
            'satuan'              => ['required', 'string', 'max:20'],
            'harga_beli_terakhir' => ['nullable', 'numeric', 'min:0'],
            'qty_minimum'         => ['nullable', 'numeric', 'min:0'],
            'is_active'           => ['boolean'],
            'stok_awal'           => ['nullable', 'array'],
            'stok_awal.*'         => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'kode_item'        => 'Kode Item',
            'nama_item'        => 'Nama Item',
            'item_category_id' => 'Kategori',
            'tipe'             => 'Tipe',
            'satuan'           => 'Unit',
        ];
    }
}
