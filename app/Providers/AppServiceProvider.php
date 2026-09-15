<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

use App\Models\Stock;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Item;
use App\Models\User;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Supplier;
use App\Models\Karyawan;
use App\Models\Penggajian;
use App\Models\Cuti;
use App\Models\Asset;
use App\Models\Cabang;

use App\Observers\StockObserver;
use App\Observers\StockRequestObserver;
use App\Observers\StockRequestItemObserver;
use App\Observers\StockTransferObserver;
use App\Observers\StockTransferItemObserver;
use App\Observers\ItemObserver;
use App\Observers\UserObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\PurchaseOrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\OrderItemObserver;
use App\Observers\SupplierObserver;
use App\Observers\KaryawanObserver;
use App\Observers\PenggajianObserver;
use App\Observers\CutiObserver;
use App\Observers\AssetObserver;
use App\Observers\CabangObserver;
use App\Observers\TransaksiKeuanganObserver;
use App\Observers\PengaturanUmumObserver;
use App\Models\PengaturanUmum;
use App\Models\TransaksiKeuangan;
use App\Models\RecurringTransaksi;
use App\Listeners\SendBellPusherSignal;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Gunakan Bootstrap 5 untuk pagination (bukan Tailwind default Laravel 12)
        Paginator::useBootstrapFive();

        // @rupiah($amount) — tampilkan nominal Rupiah terformat, contoh: @rupiah($harga) → Rp 5.000.000
        Blade::directive('rupiah', function ($expression) {
            return "<?php echo 'Rp ' . number_format((int) round((float) ($expression ?? 0)), 0, ',', '.'); ?>";
        });

        // Owner bypass semua gate check
        Gate::before(function (User $user, string $ability) {
            if ($user->role?->value === 'owner') {
                return true;
            }
        });

        // Daftarkan semua permission dari DB sebagai Gate
        // Sehingga bisa pakai @can('stok.view') di Blade
        try {
            $permissionNames = Cache::remember(
                'all_permission_names', 3600,
                fn () => \App\Models\Permission::pluck('name')->toArray()
            );
            foreach ($permissionNames as $permName) {
                Gate::define($permName, function (User $user) use ($permName) {
                    return $user->hasPermission($permName);
                });
            }
        } catch (\Exception $e) {
            // Tabel belum ada saat migrasi pertama kali
        }

        Stock::observe(StockObserver::class);
        StockRequest::observe(StockRequestObserver::class);
        StockRequestItem::observe(StockRequestItemObserver::class);
        StockTransfer::observe(StockTransferObserver::class);
        StockTransferItem::observe(StockTransferItemObserver::class);
        Item::observe(ItemObserver::class);
        PengaturanUmum::observe(PengaturanUmumObserver::class);
        User::observe(UserObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        Supplier::observe(SupplierObserver::class);
        Karyawan::observe(KaryawanObserver::class);
        Penggajian::observe(PenggajianObserver::class);
        Cuti::observe(CutiObserver::class);
        Asset::observe(AssetObserver::class);
        Cabang::observe(CabangObserver::class);
        TransaksiKeuangan::observe(TransaksiKeuanganObserver::class);

        // Pusher real-time: kirim signal ke bell lonceng setiap notifikasi database masuk
        Event::listen(NotificationSent::class, SendBellPusherSignal::class);

        // On-request recurring trigger: generate sekali per hari tanpa perlu cron
        $this->triggerRecurringIfNeeded();
    }

    private function triggerRecurringIfNeeded(): void
    {
        $cacheKey = 'recurring_run_' . now()->toDateString();
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, true, now()->endOfDay());

        try {
            $recurrings = RecurringTransaksi::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->jatuhTempo()
                ->get();

            foreach ($recurrings as $r) {
                DB::transaction(fn () => $r->generateTransaksi());
            }
        } catch (\Exception $e) {
            Log::error('Recurring auto-generate failed: ' . $e->getMessage());
        }
    }
}
