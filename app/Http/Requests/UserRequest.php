<?php

namespace App\Http\Requests;

use App\Enums\RoleUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) return false;

        // Owner bisa kelola semua user
        if ($user->canAccessAllBranches()) return true;

        // Manajer Cabang hanya bisa buat/edit user di cabangnya
        if ($user->role === RoleUser::ManajerCabang) return true;

        return false;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name'        => ['required', 'string', 'max:100'],
            'email'       => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'telepon'     => ['nullable', 'string', 'max:20'],
            'role'        => ['required', Rule::enum(RoleUser::class)],
            'is_active'   => ['sometimes', 'boolean'],
            'cabang_ids'  => ['nullable', 'array'],
            'cabang_ids.*'=> ['exists:cabangs,id'],
            'default_cabang_id' => ['nullable', 'exists:cabangs,id'],
            'password'    => $isUpdate
                ? ['nullable', 'confirmed', Password::min(8)]
                : ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Nama lengkap wajib diisi.',
            'email.required'    => 'Email wajib diisi.',
            'email.unique'      => 'Email sudah digunakan oleh user lain.',
            'role.required'     => 'Role wajib dipilih.',
            'password.required' => 'Password wajib diisi untuk user baru.',
            'password.confirmed'=> 'Konfirmasi password tidak cocok.',
            'password.min'      => 'Password minimal 8 karakter.',
            'cabang_ids.*.exists' => 'Salah satu cabang yang dipilih tidak valid.',
        ];
    }
}
