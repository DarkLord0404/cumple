<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    private function authorizeAdministrator(Request $request): void
    {
        abort_unless($request->user()->role === 'administrator', 403);
    }

    public function index(Request $request): View
    {
        $this->authorizeAdministrator($request);

        return view('users.index', [
            'users' => User::with('area')->orderBy('name')->paginate(20),
            'areas' => Area::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'area_id' => ['nullable', 'exists:areas,id'],
            'role' => ['required', Rule::in(['administrator', 'coordinator', 'quality', 'collaborator'])],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Escribe el nombre completo.',
            'email.required' => 'Escribe el correo del usuario.',
            'email.email' => 'El correo no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario con este correo.',
            'area_id.exists' => 'El área seleccionada no es válida.',
            'role.in' => 'El rol seleccionado no es válido.',
            'password.required' => 'Escribe una contraseña temporal.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);
        $data['password'] = Hash::make($data['password']);
        $data['email_verified_at'] = now();
        User::create($data);

        return back()->with('status', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'area_id' => ['nullable', 'exists:areas,id'],
            'role' => ['required', Rule::in(['administrator', 'coordinator', 'quality', 'collaborator'])],
            'is_active' => ['required', 'boolean'],
        ]);
        $user->update($data);

        return back()->with('status', 'Usuario actualizado.');
    }
}
