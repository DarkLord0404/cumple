<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationDefaults;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, OrganizationDefaults $defaults): RedirectResponse
    {
        $request->validate([
            'organization_name' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ]);

        $user = DB::transaction(function () use ($request, $defaults): User {
            $baseSlug = Str::slug($request->string('organization_name')) ?: 'organizacion';
            $slug = $baseSlug;
            $suffix = 2;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }
            $organization = Organization::create([
                'name' => $request->string('organization_name')->trim(),
                'slug' => $slug,
                'contact_email' => $request->string('email')->lower()->trim(),
                'is_active' => true,
            ]);
            $defaults->createFor($organization);

            return User::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'name' => $request->string('name')->trim(),
                'email' => $request->string('email')->lower()->trim(),
                'password' => Hash::make($request->password),
                'role' => 'administrator',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
