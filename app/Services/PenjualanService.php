<?php

namespace App\Services;

use App\Enums\KategoriTransaksi;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Enums\TipePembayaran;
use App\Enums\TipeStockMovement;
use App\Enums\TipeTransaksi;
use App\Enums\TipeTransaksiKeuangan;
use App\Events\AntrianCreated;
use App\Models\Cabang;
use App\Models\Item;
use App\Models\ItemVariant;
use App\Models\Kas;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPayment;
use App\Models\StockMovement;
use App\Models\TransaksiKeuangan;
use Illuminate\Support\Facades\DB;

class PenjualanService
{
    public function __construct(private StokService $stokService) {}

    /**
     * Tahap 3 D'mentai — "Charge langsung": buat order BARU dan langsung
     * finalisasi (stok+kas) dalam 1 pemanggilan, dipakai kalau kasir tidak
     * lewat Save Bill dulu. Secara internal cuma gabungan simpanBill()+
     * chargeBill() supaya logic stok/kas/pembayaran cuma ada SATU sumber
     * (tidak diduplikasi antara 2 alur).
     */
    public function buatOrder(array $data, int $cabangId): Order
    {
        $order = DB::transaction(function () use ($data, $cabangId) {
            $order = $this->simpanBillInternal($data, $cabangId, StatusOrder::Pending);

            return $this->chargeBillInternal($order, $data['payments'], $data);
        });

        if ($order->nomor_antrian !== null) {
            AntrianCreated::dispatch($order->load('items'));
        }

        return $order;
    }

    /**
     * Tahap 3 D'mentai — Save Bill: simpan keranjang sebagai bill (status
     * Pending), TIDAK menyentuh stok/kas sama sekali. Bisa dilanjut bayar
     * kapan saja lewat chargeBill().
     */
    public function simpanBill(array $data, int $cabangId): Order
    {
        return DB::transaction(fn () => $this->simpanBillInternal($data, $cabangId, StatusOrder::Pending));
    }

    /**
     * Tahap 3 D'mentai — finalisasi bill yang sudah tersimpan (dari
     * simpanBill() atau dari buatOrder() secara internal): cek+potong stok,
     * proses split payment, catat kas, set status Selesai.
     */
    public function chargeBill(Order $order, array $paymentData): Order
    {
        if ($order->status !== StatusOrder::Pending) {
            throw new \Exception('Order ini sudah diproses sebelumnya (bukan lagi berstatus Pending).');
        }

        $order = DB::transaction(fn () => $this->chargeBillInternal($order, $paymentData['payments'], $paymentData));

        if ($order->nomor_antrian !== null) {
            AntrianCreated::dispatch($order->load('items'));
        }

        return $order;
    }

    // =========================================================================
    // INTERNAL — dipanggil dari dalam DB::transaction() milik caller
    // =========================================================================

    private function simpanBillInternal(array $data, int $cabangId, StatusOrder $status): Order
    {
        $cabang = Cabang::findOrFail($cabangId);
        $tipeTransaksi = TipeTransaksi::from($data['tipe_transaksi']);

        if (! $cabang->tipeTransaksiAktif($tipeTransaksi)) {
            throw new \Exception("Tipe transaksi \"{$tipeTransaksi->label()}\" tidak aktif untuk outlet ini.");
        }

        if ($tipeTransaksi === TipeTransaksi::DineIn && $cabang->nomor_meja_aktif && empty($data['nomor_meja'])) {
            throw new \Exception('Nomor meja wajib diisi untuk transaksi Dine-in di outlet ini.');
        }

        [$itemRows, $totalHarga] = $this->resolveItemRows($data['items']);

        $diskon = (float) ($data['diskon'] ?? 0);
        $subtotalSetelahDiskon = max(0, $totalHarga - $diskon);

        $serviceCharge = round($subtotalSetelahDiskon * ((float) $cabang->service_charge_persen / 100), 2);
        $takeAwayFee = $tipeTransaksi === TipeTransaksi::Takeaway ? (float) $cabang->take_away_fee : 0.0;
        $totalBayar = $subtotalSetelahDiskon + $serviceCharge + $takeAwayFee;

        // Validasi stok SEMUA item dulu sebelum buat record apapun (Approach
        // B, dipertahankan dari alur lama) — resep-aware: item dengan resep
        // dicek per-bahan, tanpa resep dicek langsung stok item itu sendiri.
        $this->cekStokSemuaItem($itemRows, $cabangId);

        $today = today();
        $nomorAntrian = null;
        $statusProduksi = null;
        $tampilDiAntrian = array_key_exists('tampil_di_antrian', $data)
            ? (bool) $data['tampil_di_antrian']
            : true;

        // Frozen sengaja TIDAK masuk antrian produksi (siap saji instan,
        // beda dari dine-in/takeaway yang perlu disiapkan dulu).
        if ($cabang->antrian_produksi_aktif && $tampilDiAntrian && $tipeTransaksi !== TipeTransaksi::Frozen) {
            $nomorAntrian = Order::generateNomorAntrian($cabangId, $today);
            $statusProduksi = 'menunggu';
        }

        $order = Order::create([
            'cabang_id'         => $cabangId,
            'nomor_order'       => $this->generateNomorOrder($cabang),
            'tanggal_order'     => $today,
            'tipe_order'        => TipeOrder::Penjualan,
            'tipe_transaksi'    => $tipeTransaksi,
            'nomor_meja'        => $tipeTransaksi === TipeTransaksi::DineIn ? ($data['nomor_meja'] ?? null) : null,
            'tanggal_expired_frozen' => $tipeTransaksi === TipeTransaksi::Frozen ? ($data['tanggal_expired_frozen'] ?? null) : null,
            'pelanggan_id'      => $data['pelanggan_id'] ?? null,
            'nama_pelanggan'    => $data['nama_pelanggan'] ?? null,
            'telepon_pelanggan' => $data['telepon_pelanggan'] ?? null,
            'total_harga'       => $totalHarga,
            'diskon'            => $diskon,
            'service_charge'    => $serviceCharge,
            'take_away_fee'     => $takeAwayFee,
            'total_bayar'       => $totalBayar,
            'jumlah_bayar'      => 0,
            'kembalian'         => 0,
            'tipe_pembayaran'   => TipePembayaran::Tunai, // placeholder, diisi ulang saat charge
            'status'            => $status,
            'catatan'           => $data['catatan'] ?? null,
            'kasir_id'          => auth()->id(),
            'bukti_pembayaran'  => $data['bukti_pembayaran'] ?? null,
            'nomor_antrian'     => $nomorAntrian,
            'status_produksi'   => $statusProduksi,
            'tampil_di_antrian' => $tampilDiAntrian,
            'parent_order_id'   => $data['parent_order_id'] ?? null,
        ]);

        foreach ($itemRows as $row) {
            OrderItem::create([
                'order_id'        => $order->id,
                'item_id'         => $row['item_id'],
                'item_variant_id' => $row['item_variant_id'],
                'nama_item'       => $row['nama_item'],
                'qty'             => $row['qty'],
                'satuan'          => $row['satuan'],
                'harga_satuan'    => $row['harga_satuan'],
                'total_harga'     => $row['total_harga'],
                'hpp'             => 0, // diisi saat charge (stok baru dipotong saat itu)
                'berat_daging'    => $row['berat_daging'] ?? null,
                'jenis_olahan'    => $row['jenis_olahan'] ?? null,
                'catatan'         => $row['catatan'] ?? null,
            ]);
        }

        return $order;
    }

    private function chargeBillInternal(Order $order, array $payments, array $data): Order
    {
        $cabangId = $order->cabang_id;
        $items = $order->items()->get();

        // Cek ulang stok tepat sebelum potong — bisa saja berubah sejak
        // Save Bill dibuat (order lain menghabiskan stok duluan).
        $itemRowsUntukCek = $items->map(fn ($oi) => [
            'item_id' => $oi->item_id,
            'qty'     => (float) $oi->qty,
        ])->all();
        $this->cekStokSemuaItem($itemRowsUntukCek, $cabangId);

        foreach ($items as $orderItem) {
            if (empty($orderItem->item_id)) continue;

            $masterItem = Item::find($orderItem->item_id);
            if (! $masterItem) continue;

            $hpp = $this->potongStokUntukItem($masterItem, (float) $orderItem->qty, $cabangId, $order);
            $orderItem->update(['hpp' => $hpp]);
        }

        $totalPayments = array_sum(array_map(fn ($p) => (float) $p['jumlah'], $payments));
        if ($totalPayments < (float) $order->total_bayar) {
            $kurang = number_format((float) $order->total_bayar - $totalPayments, 0, ',', '.');
            throw new \Exception("Jumlah bayar kurang Rp {$kurang}.");
        }

        [$kasIdUtama, $tipePembayaranSummary, $totalDibayar] = $this->prosesPembayaran($order, $payments, $cabangId);

        $kembalian = max(0, $totalDibayar - (float) $order->total_bayar);

        $order->update([
            'jumlah_bayar'    => $totalDibayar,
            'kembalian'       => $kembalian,
            'kas_id'          => $kasIdUtama,
            'tipe_pembayaran' => $tipePembayaranSummary,
            'status'          => StatusOrder::Selesai,
        ]);

        return $order->fresh(['items', 'payments']);
    }

    /**
     * Resolve baris item mentah dari request jadi array siap-simpan: harga
     * varian di-override server-side (defense-in-depth, cegah manipulasi
     * harga dari client), subtotal dihitung, total keseluruhan diakumulasi.
     *
     * @return array{0: array, 1: float} [$itemRows, $totalHarga]
     */
    private function resolveItemRows(array $items): array
    {
        $totalHarga = 0.0;
        $itemRows = [];

        foreach ($items as $row) {
            $masterItem = ! empty($row['item_id']) ? Item::find((int) $row['item_id']) : null;
            $variant = ! empty($row['item_variant_id']) ? ItemVariant::find((int) $row['item_variant_id']) : null;

            $hargaSatuan = (float) $row['harga_satuan'];
            if ($variant && $variant->item_id === $masterItem?->id) {
                $hargaSatuan = $variant->harga_efektif;
            }

            // Defense-in-depth satuan (dipertahankan dari alur lama) — satuan
            // SELALU ikut master item kalau item_id ada.
            if ($masterItem) {
                $masterSatuanLower = strtolower(trim($masterItem->satuan));
                $submittedSatuanLower = strtolower(trim($row['satuan'] ?? ''));
                $isMasterBeratBased = $masterSatuanLower === 'kg';
                $submittedKlaimBerat = in_array($submittedSatuanLower, ['kg', 'ons', 'gram'], true);

                if (! $isMasterBeratBased && $submittedKlaimBerat) {
                    throw new \Exception(
                        "Item '{$masterItem->nama_item}' satuannya {$masterItem->satuan} (bukan satuan berat), " .
                        'tapi dikirim dengan satuan berat (' . ($row['satuan'] ?? '?') . '). ' .
                        'Muat ulang halaman POS dan input ulang baris ini.'
                    );
                }
            }

            $qty = (float) $row['qty'];
            $subtotal = $qty * $hargaSatuan;
            $totalHarga += $subtotal;

            $itemRows[] = [
                'item_id'         => $row['item_id'] ?? null,
                'item_variant_id' => $variant?->id,
                'nama_item'       => $row['nama_item'],
                'qty'             => $qty,
                'satuan'          => $masterItem->satuan ?? ($row['satuan'] ?? 'pcs'),
                'harga_satuan'    => $hargaSatuan,
                'total_harga'     => $subtotal,
                'berat_daging'    => $row['berat_daging'] ?? null,
                'jenis_olahan'    => $row['jenis_olahan'] ?? null,
                'catatan'         => $row['catatan'] ?? null,
            ];
        }

        return [$itemRows, $totalHarga];
    }

    /**
     * Cek stok cukup untuk SEMUA baris item, resep-aware:
     *  - Item PUNYA resep produksi -> cek tiap bahan baku komposisinya.
     *  - Item TIDAK punya resep -> cek stok item itu sendiri (kalau
     *    tipe-nya memang dipotong stoknya: bahan_baku/kemasan/produk_jual).
     * Dipanggil SEBELUM ada write apapun ke DB (Approach B).
     */
    private function cekStokSemuaItem(array $itemRows, int $cabangId): void
    {
        foreach ($itemRows as $row) {
            if (empty($row['item_id'])) continue;

            $masterItem = Item::find((int) $row['item_id']);
            if (! $masterItem) continue;

            $qty = (float) $row['qty'];

            if ($masterItem->resep) {
                $this->cekResepCukup($masterItem, $qty, $cabangId);
                continue;
            }

            // Tahap 2.5 D'mentai — 'produk_jadi' -> 'produk_jual' (1:1 rename).
            if (! in_array($masterItem->tipe, ['bahan_baku', 'kemasan', 'produk_jual'], true)) continue;

            $stokTersedia = $this->stokService->getStok($masterItem->id, $cabangId);
            if ($stokTersedia < $qty) {
                throw new \Exception(
                    "Stok '{$masterItem->nama_item}' tidak mencukupi. " .
                    "Tersedia: {$stokTersedia} {$masterItem->satuan}, dibutuhkan: {$qty} {$masterItem->satuan}."
                );
            }
        }
    }

    /** Cek stok tiap bahan baku komposisi resep 1 item, throw pesan spesifik kalau ada yang kurang. */
    /**
     * Cek stok cukup, resep-aware. Baris resep "linked" (Import dari Bumbu
     * Pusat, lihat ResepBumbuItem::expandKeBahanMentah()) di-expand jadi
     * kebutuhan bahan MENTAH-nya sebelum dicek — bumbu sendiri tidak punya
     * stok, yang genuinely dipotong adalah bahan di dalamnya.
     */
    private function cekResepCukup(Item $item, float $qty, int $cabangId): void
    {
        $resep = $item->resep;
        if (! $resep) return;

        // Akumulasi per item_id dulu (2 baris resep bisa kebetulan pakai
        // bahan mentah yang sama, mis. langsung + lewat 2 bumbu berbeda).
        $kebutuhan = [];
        foreach ($resep->items as $resepItem) {
            foreach ($resepItem->expandKeBahanMentah() as $bahanMentah) {
                $kebutuhan[$bahanMentah['item_id']] = ($kebutuhan[$bahanMentah['item_id']] ?? 0) + $bahanMentah['butuh_kg'] * $qty;
            }
        }

        foreach ($kebutuhan as $itemId => $butuh) {
            $bahan = Item::find($itemId);
            if (! $bahan) continue;

            $tersedia = $this->stokService->getStok($itemId, $cabangId);
            if ($tersedia < $butuh) {
                throw new \Exception(
                    "Stok '{$bahan->nama_item}' tidak mencukupi untuk memproses '{$item->nama_item}'. " .
                    "Tersedia: {$tersedia} {$bahan->satuan}, dibutuhkan: " . round($butuh, 3) . " {$bahan->satuan}."
                );
            }
        }
    }

    /**
     * Potong stok utk 1 item (resep-aware) dan kembalikan HPP-nya.
     *  - Punya resep -> potong tiap bahan baku, HPP = jumlah HPP semua bahan
     *    (representasi COGS yang lebih akurat daripada harga item tunggal).
     *  - Tidak punya resep, tipe dipotong stok -> potong item itu sendiri.
     *  - Selain itu (mis. Item Tambahan tanpa resep) -> tidak potong stok, HPP 0.
     */
    private function potongStokUntukItem(Item $item, float $qty, int $cabangId, Order $order): float
    {
        if ($item->resep) {
            // Sama seperti cekResepCukup(): akumulasi per item_id dulu
            // (expand baris linked ke bahan mentahnya) supaya bahan yang
            // kebetulan dipakai 2x (langsung + lewat bumbu) cuma 1x panggilan
            // StokService::keluar() per item -- movement/HPP FIFO tetap benar.
            $kebutuhan = [];
            foreach ($item->resep->items as $resepItem) {
                foreach ($resepItem->expandKeBahanMentah() as $bahanMentah) {
                    $kebutuhan[$bahanMentah['item_id']] = ($kebutuhan[$bahanMentah['item_id']] ?? 0) + $bahanMentah['butuh_kg'] * $qty;
                }
            }

            $totalHpp = 0.0;
            foreach ($kebutuhan as $itemId => $butuh) {
                if ($butuh <= 0) continue;

                $totalHpp += $this->stokService->keluar(
                    $itemId,
                    $cabangId,
                    $butuh,
                    "Komposisi resep '{$item->nama_item}' — Order {$order->nomor_order}",
                    'order',
                    $order->id
                );
            }

            return $totalHpp;
        }

        // Tahap 2.5 D'mentai — 'produk_jadi' -> 'produk_jual' (1:1 rename).
        if (in_array($item->tipe, ['bahan_baku', 'kemasan', 'produk_jual'], true)) {
            return $this->stokService->keluar(
                $item->id,
                $cabangId,
                $qty,
                'Penjualan Order ' . $order->nomor_order,
                'order',
                $order->id
            );
        }

        return 0.0;
    }

    /**
     * Split Payment: proses N metode bayar untuk 1 order. Tiap metode
     * resolve ke Kas kategori masing-masing (Gojek/Grab -> kategori
     * "transfer", keputusan Owner Tahap 3), bikin 1 OrderPayment + 1
     * TransaksiKeuangan per baris, kas ter-increment sesuai bagiannya.
     *
     * @return array{0: ?int, 1: TipePembayaran, 2: float} [$kasIdUtama, $tipePembayaranSummary, $totalDibayar]
     */
    private function prosesPembayaran(Order $order, array $payments, int $cabangId): array
    {
        $kasIdUtama = null;
        $tipePembayaranSummary = null;
        $totalDibayar = 0.0;
        $isSplit = count($payments) > 1;

        foreach ($payments as $index => $payment) {
            $metode = TipePembayaran::from($payment['metode']);
            $jumlah = (float) $payment['jumlah'];
            $totalDibayar += $jumlah;

            $kas = Kas::where('cabang_id', $cabangId)
                ->where('default_untuk', $metode->kasKategori())
                ->where('is_active', true)
                ->first();

            if (! $kas) {
                $kategoriLabel = TipePembayaran::tryFrom($metode->kasKategori())?->label() ?? $metode->kasKategori();
                throw new \Exception(
                    "Cabang belum punya Kas default untuk pembayaran {$kategoriLabel}, hubungi Admin/Owner untuk membuat atau mengaktifkan Kas tersebut."
                );
            }

            if ($index === 0) {
                $kasIdUtama = $kas->id;
                $tipePembayaranSummary = $metode;
            }

            OrderPayment::create([
                'order_id'   => $order->id,
                'metode'     => $metode,
                'jumlah'     => $jumlah,
                'keterangan' => $isSplit ? 'Split payment' : null,
            ]);

            $kas->increment('saldo_sekarang', $jumlah);

            TransaksiKeuangan::create([
                'cabang_id'         => $cabangId,
                'kas_id'            => $kas->id,
                'nomor_transaksi'   => $this->generateNomorTransaksi($order->nomor_order) . ($isSplit ? '-' . ($index + 1) : ''),
                'tanggal_transaksi' => today(),
                'tipe'              => TipeTransaksiKeuangan::Pemasukan,
                'kategori'          => KategoriTransaksi::Penjualan,
                'keterangan'        => 'Order ' . $order->nomor_order . ($order->nama_pelanggan ? ' — ' . $order->nama_pelanggan : '') . ($isSplit ? ' (' . $metode->label() . ')' : ''),
                'jumlah'            => $jumlah,
                'referensi_type'    => 'order',
                'referensi_id'      => $order->id,
                'created_by'        => auth()->id(),
            ]);
        }

        return [$kasIdUtama, $tipePembayaranSummary, $totalDibayar];
    }

    public function batalkan(Order $order): void
    {
        DB::transaction(function () use ($order) {
            if ($order->status === StatusOrder::Selesai) {
                // Gunakan StockMovement sebagai source of truth:
                // hanya kembalikan stok yang BENAR-BENAR pernah dikurangi saat order dibuat.
                // Ini mencegah stok dikembalikan 2x (jika batalkan dipanggil ulang)
                // dan mencegah stok dikembalikan untuk item jasa_giling yang tidak pernah dikurangi.
                $keluarMovements = StockMovement::where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->where('tipe', TipeStockMovement::Keluar->value)
                    ->orderBy('id')
                    ->get();

                // Antrian order_items per item_id (urut id) — dipasangkan 1:1 ke
                // movement per posisi kemunculan, supaya benar walau 1 order
                // punya item_id yang sama berulang di baris berbeda.
                $orderItemQueue = OrderItem::where('order_id', $order->id)
                    ->whereNotNull('item_id')
                    ->orderBy('id')
                    ->get()
                    ->groupBy('item_id')
                    ->map(fn ($group) => $group->values());
                $orderItemCursor = [];

                foreach ($keluarMovements as $movement) {
                    $lokasiId = $movement->lokasi_asal_id ?? $order->cabang_id;
                    if (!$lokasiId) continue;

                    // stock_movements TIDAK menyimpan batch_id yang dikonsumsi
                    // (relasi cuma via item_id+lokasi_id, lihat Rule bisnis #34),
                    // jadi tidak mungkin restore ke batch ASAL yang persis sama.
                    // Solusi: bikin 1 batch BARU per item, senilai HPP yang
                    // BENAR-BENAR tercatat di order_item saat penjualan asli
                    // (order_items.hpp / order_items.qty = harga rata-rata per
                    // unit) — ini akurat ke nilai (bukan cuma qty) TANPA perlu
                    // tahu batch mana persisnya, dan otomatis benar juga untuk
                    // kasus original sale sempat kena fallback harga_beli_terakhir
                    // di consumeBatchesFifo() (sudah ke-blend di hpp tersimpan).
                    //
                    // CATATAN Tahap 3: dengan resep-aware deduction, movement
                    // 'keluar' sekarang tercatat per BAHAN BAKU (bukan per
                    // item jadi) kalau item itu punya resep — pairing ke
                    // order_items di bawah tetap valid karena kita pairing
                    // by item_id persis yang tercatat di movement itu sendiri
                    // (bisa jadi item bahan baku, bukan item jadi yang dibeli
                    // pelanggan) — qty/hpp movement tetap dikembalikan benar
                    // per bahan, terlepas dari order_items mana asalnya.
                    $itemIdKey = $movement->item_id;
                    $cursorIdx = $orderItemCursor[$itemIdKey] ?? 0;
                    $orderItem = $orderItemQueue->get($itemIdKey)?->get($cursorIdx);
                    $orderItemCursor[$itemIdKey] = $cursorIdx + 1;

                    $hargaBeli = ($orderItem && (float) $orderItem->qty > 0)
                        ? (float) $orderItem->hpp / (float) $orderItem->qty
                        : (float) (Item::find($movement->item_id)?->harga_beli_terakhir ?? 0);

                    $this->stokService->masuk(
                        $movement->item_id,
                        $lokasiId,
                        (float) $movement->qty,
                        'Pembatalan Order ' . $order->nomor_order,
                        'order',
                        $order->id,
                        $hargaBeli
                    );
                }

                // Balikkan saldo kas utk SEMUA payment (split payment aware)
                // sebelum hapus catatannya.
                $trxList = TransaksiKeuangan::where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->get();
                foreach ($trxList as $trxKeuangan) {
                    if ($trxKeuangan->kas_id) {
                        Kas::where('id', $trxKeuangan->kas_id)
                            ->decrement('saldo_sekarang', (float) $trxKeuangan->jumlah);
                    }
                }

                // Hapus catatan keuangan terkait order ini
                TransaksiKeuangan::where('referensi_type', 'order')
                    ->where('referensi_id', $order->id)
                    ->delete();
            }
            $order->update(['status' => StatusOrder::Dibatalkan]);
        });
    }

    public function kembalikan(Order $order): void
    {
        if ($order->status !== StatusOrder::Dibatalkan) {
            throw new \Exception('Hanya order dengan status Dibatalkan yang bisa dikembalikan.');
        }

        DB::transaction(function () use ($order) {
            // LANGKAH 1: Pre-validasi stok sebelum ubah apapun
            $pembatalanMovements = StockMovement::where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->where('tipe', TipeStockMovement::Masuk->value)
                ->where('catatan', 'like', 'Pembatalan Order %')
                ->get();

            foreach ($pembatalanMovements as $m) {
                $lokasiId = $m->lokasi_tujuan_id ?? $order->cabang_id;
                $stokAda  = $this->stokService->getStok($m->item_id, $lokasiId);
                if ($stokAda < (float) $m->qty) {
                    $nama = Item::find($m->item_id)?->nama_item ?? "item #{$m->item_id}";
                    throw new \Exception(
                        "Stok '{$nama}' tidak cukup untuk kembalikan order. " .
                        "Tersedia: {$stokAda}, dibutuhkan: {$m->qty}. " .
                        "Stok mungkin sudah dipakai order lain setelah pembatalan."
                    );
                }
            }

            // LANGKAH 2: Kurangi stok kembali (undo pembatalan movements)
            foreach ($pembatalanMovements as $m) {
                $lokasiId = $m->lokasi_tujuan_id ?? $order->cabang_id;
                $this->stokService->keluar(
                    $m->item_id, $lokasiId, (float) $m->qty,
                    'Kembalikan Order ' . $order->nomor_order, 'order', $order->id
                );
            }

            // LANGKAH 3: Restore SEMUA TransaksiKeuangan (split payment aware)
            // + kembalikan saldo kas masing-masing.
            // withoutGlobalScopes() wajib: withTrashed() saja tidak cukup karena CabangScope
            // tetap aktif dan bisa memfilter record berdasarkan session('active_cabang_id').
            // Defensive: tambah cabang_id dari $order (bukan dari session) sebagai filter eksplisit.
            $trxList = TransaksiKeuangan::withoutGlobalScopes()
                ->where('referensi_type', 'order')
                ->where('referensi_id', $order->id)
                ->where('cabang_id', $order->cabang_id)
                ->whereNotNull('deleted_at')
                ->get();

            foreach ($trxList as $trxKeuangan) {
                if ($trxKeuangan->kas_id) {
                    Kas::withoutGlobalScopes()
                        ->where('id', $trxKeuangan->kas_id)
                        ->increment('saldo_sekarang', (float) $trxKeuangan->jumlah);
                }
                $trxKeuangan->restore();
            }

            // LANGKAH 4: Update status order
            $order->update(['status' => StatusOrder::Selesai]);
        });
    }

    public function generateNomorOrder(Cabang $cabang): string
    {
        $tahun = (int) date('Y');

        // Ambil order terakhir cabang ini di tahun ini (urutkan by ID untuk akurasi)
        // withTrashed() wajib: soft-deleted orders masih punya unique constraint
        // di transaksi_keuangans, skip = sequence restart = duplicate entry
        $lastOrder = Order::withTrashed()
            ->where('cabang_id', $cabang->id)
            ->whereYear('created_at', $tahun)
            ->orderByDesc('id')
            ->value('nomor_order');

        $nextSeq = 1;
        if ($lastOrder && preg_match('/(\d+)$/', $lastOrder, $m)) {
            $nextSeq = ((int) $m[1]) + 1;
        }

        // Format baru: KODE-YYYY-NNNNN (tanpa prefix INV-)
        return $cabang->kode_cabang . '-' . $tahun . '-' . str_pad($nextSeq, 5, '0', STR_PAD_LEFT);
    }

    public function generateNomorTransaksi(string $nomorOrder): string
    {
        return 'TRX-' . $nomorOrder;
    }
}
