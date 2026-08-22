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
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
            'role' => ['required', Rule::in(['administrator', ...User::COORDINATOR_ROLES, 'quality', 'collaborator'])],
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
        $user = User::create($data);
        $this->syncCoordinatedArea($user);

        return back()->with('status', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'area_id' => ['nullable', Rule::exists('areas', 'id')->where('organization_id', $request->user()->organization_id)],
            'role' => ['required', Rule::in(['administrator', ...User::COORDINATOR_ROLES, 'quality', 'collaborator'])],
            'is_active' => ['required', 'boolean'],
        ]);
        if ($user->is($request->user()) && ! $data['is_active']) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propia cuenta de administrador.']);
        }
        $user->update($data);
        $this->syncCoordinatedArea($user);

        return back()->with('status', 'Usuario actualizado.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdministrator($request);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);
        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('status', "Contraseña actualizada para {$user->name}.");
    }

    private function syncCoordinatedArea(User $user): void
    {
        Area::where('coordinator_id', $user->id)
            ->when($user->isCoordinator() && $user->is_active && $user->area_id,
                fn ($query) => $query->whereKeyNot($user->area_id))
            ->update(['coordinator_id' => null]);

        if ($user->isCoordinator() && $user->is_active && $user->area_id) {
            Area::whereKey($user->area_id)->update(['coordinator_id' => $user->id]);
        }
    }
}
