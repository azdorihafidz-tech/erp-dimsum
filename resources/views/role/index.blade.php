@extends('layouts.app')
@section('title', 'Kelola Role & Permissions')

@push('styles')
<style>
.role-card { border:1px solid #e2e8f0; border-radius:12px; background:white; }
.perm-check { display:flex; align-items:center; gap:.5rem; padding:.35rem .5rem; border-radius:6px; cursor:pointer; transition:background .1s; font-size:.8rem; }
.perm-check:hover { background:#f8fafc; }
.perm-group-title { font-size:.65rem; text-transform:uppercase; letter-spacing:.08em; color:#94a3b8; font-weight:700; padding:.5rem 0 .25rem; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-center justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-0" style="color:#1e293b"><i class="bi bi-shield-check me-2 text-primary"></i>Role & Permissions</h4>
        <nav aria-label="breadcrumb"><ol class="breadcrumb mb-0" style="font-size:0.8rem">
            <li class="breadcrumb-item"><a href="{{ route('user.index') }}" class="text-decoration-none">User</a></li>
            <li class="breadcrumb-item active">Role</li>
        </ol></nav>
    </div>
    <a href="{{ route('user.index') }}" class="btn btn-outline-secondary">
        <i class="bi bi-people me-1"></i><span class="d-none d-sm-inline">Daftar User</span>
    </a>
</div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
    <i class="bi bi-check-circle-fill me-2"></i>{!! session('success') !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

<div class="alert alert-info d-flex gap-2 mb-4" style="font-size:0.85rem">
    <i class="bi bi-info-circle-fill flex-shrink-0 mt-1"></i>
    <span>Role bersifat tetap (enum) dan tidak dapat ditambah/dihapus. Yang bisa diatur adalah <strong>permissions</strong> yang dimiliki setiap role.</span>
</div>

{{-- Role tabs --}}
<ul class="nav nav-tabs mb-0 border-0" id="roleTabs" role="tablist" style="flex-wrap:wrap">
    @foreach($roles as $i => $role)
    <li class="nav-item" role="presentation">
        <button class="nav-link {{ $i === 0 ? 'active' : '' }}"
            data-bs-toggle="tab"
            data-bs-target="#tab-{{ $role['value'] }}"
            type="button" role="tab">
            {{ $role['label'] }}
            <span class="badge bg-secondary-subtle text-secondary ms-1" style="font-size:0.65rem">{{ $role['user_count'] }}</span>
        </button>
    </li>
    @endforeach
</ul>

<div class="tab-content" id="roleTabContent">
    @foreach($roles as $i => $role)
    <div class="tab-pane fade {{ $i === 0 ? 'show active' : '' }}" id="tab-{{ $role['value'] }}" role="tabpanel">
        <div class="card border-top-0 rounded-top-0">
            <div class="card-body p-4">
                <form method="POST" action="{{ route('role.update-permissions', $role['value']) }}">
                    @csrf

                    <div class="row g-4 mb-4">
                        {{-- Info role --}}
                        <div class="col-12 col-md-4 col-lg-3">
                            <div class="p-3 rounded-3 sticky-top" style="background:#f8fafc;border:1px solid #e2e8f0;top:80px">
                                @php
                                    $rc = match($role['value']) {
                                        'owner','admin_pusat'  => ['#fef3c7','#92400e'],
                                        'admin_gudang'         => ['#e0f2fe','#0369a1'],
                                        'manajer_cabang'       => ['#d1fae5','#065f46'],
                                        'kasir'                => ['#ede9fe','#5b21b6'],
                                        'operator_produksi'    => ['#fce7f3','#9d174d'],
                                        default                => ['#dbeafe','#1e40af'],
                                    };
                                @endphp
                                <div style="width:48px;height:48px;border-radius:10px;background:{{ $rc[0] }};display:flex;align-items:center;justify-content:center;margin-bottom:.75rem">
                                    <i class="bi bi-shield-fill" style="font-size:1.2rem;color:{{ $rc[1] }}"></i>
                                </div>
                                <div class="fw-bold" style="font-size:0.95rem">{{ $role['label'] }}</div>
                                <div class="text-muted mt-1" style="font-size:0.8rem">{{ $role['user_count'] }} user aktif</div>
                                @if($role['can_all_branch'])
                                <div class="mt-2">
                                    <span class="badge bg-warning-subtle text-warning" style="font-size:0.7rem">
                                        <i class="bi bi-globe me-1"></i>Akses Semua Cabang
                                    </span>
                                </div>
                                @endif
                                <hr>
                                <div class="d-flex flex-column gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                        <i class="bi bi-check-lg me-1"></i>Simpan Permissions
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="checkAll('{{ $role['value'] }}', true)">
                                        <i class="bi bi-check-all me-1"></i>Pilih Semua
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="checkAll('{{ $role['value'] }}', false)">
                                        <i class="bi bi-x-lg me-1"></i>Hapus Semua
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Permissions --}}
                        <div class="col-12 col-md-8 col-lg-9">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-semibold mb-0">Permissions untuk {{ $role['label'] }}</h6>
                                @php $totalPerms = collect($permissions)->flatten()->count(); @endphp
                                <small class="text-muted">{{ count($role['permission_ids']) }} / {{ $totalPerms }} dipilih</small>
                            </div>

                            @if($permissions->isEmpty())
                            <div class="text-center py-5 text-muted">
                                <i class="bi bi-shield-x" style="font-size:2.5rem;opacity:0.3"></i>
                                <p class="mt-2">Belum ada permission yang terdaftar.</p>
                            </div>
                            @else
                            <div class="row g-3">
                                @foreach($permissions as $group => $perms)
                                <div class="col-12 col-sm-6 col-xl-4">
                                    <div class="perm-group-title">
                                        <i class="bi bi-{{ match($group) {
                                            'cabang'      => 'diagram-3',
                                            'user'        => 'people',
                                            'penjualan'   => 'cart3',
                                            'stok'        => 'boxes',
                                            'pembelian'   => 'bag-plus',
                                            'keuangan'    => 'cash-stack',
                                            'hr'          => 'person-badge',
                                            'evaluasi'    => 'star-half',
                                            'aset'        => 'building-gear',
                                            'bep'         => 'graph-up-arrow',
                                            'laporan'     => 'file-earmark-bar-graph',
                                            'shift'       => 'clock',
                                            'hari_libur'  => 'calendar2-x',
                                            'keamanan'    => 'shield-lock',
                                            'akuntansi'   => 'diagram-2',
                                            default       => 'gear',
                                        } }} me-1"></i>{{ match($group) {
                                            'hr'         => 'HR & Absensi',
                                            'shift'      => 'Shift',
                                            'hari_libur' => 'Hari Libur',
                                            'keamanan'   => '🔐 Keamanan',
                                            'akuntansi'  => 'Akuntansi (COA)',
                                            default      => ucfirst($group),
                                        } }}
                                    </div>
                                    @foreach($perms as $perm)
                                    <label class="perm-check">
                                        <input type="checkbox"
                                            name="permission_ids[]"
                                            value="{{ $perm->id }}"
                                            class="form-check-input perm-cb-{{ $role['value'] }}"
                                            {{ in_array($perm->id, $role['permission_ids']) ? 'checked' : '' }}>
                                        <span>{{ $perm->display_name ?? $perm->name }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
function checkAll(role, check) {
    document.querySelectorAll('.perm-cb-' + role).forEach(cb => cb.checked = check);
}
</script>
@endpush
