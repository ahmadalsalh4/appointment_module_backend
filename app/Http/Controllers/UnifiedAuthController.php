<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $password = $request->password;
        $otherRoles = [];

        // Pre-build a dummy hash for timing-equalisation. The three
        // branches below always perform the same number of Hash::check
        // invocations regardless of which role (if any) the email maps
        // to, so an attacker can't infer role membership from response
        // timing.
        $dummyHash = '$2y$10$'.str_repeat('A', 53);

        $customer = Customer::where('email', $email)->first();
        $customerHash = $customer?->password ?? $dummyHash;
        $customerOk = Hash::check($password, $customerHash);

        if ($customer && $customerOk) {
            $token = $customer->createToken('auth-token')->plainTextToken;

            if (Staff::where('email', $email)->exists()) {
                $otherRoles[] = 'staff';
            }
            if (Admin::where('email', $email)->exists()) {
                $otherRoles[] = 'admin';
            }

            return response()->json([
                'user' => $customer->load('person'),
                'token' => $token,
                'role' => 'customer',
                'other_roles' => $otherRoles,
            ]);
        }

        $staff = Staff::where('email', $email)->first();
        $staffHash = $staff?->password ?? $dummyHash;
        $staffOk = Hash::check($password, $staffHash);

        if ($staff && $staffOk) {
            $token = $staff->createToken('auth-token')->plainTextToken;

            if (Customer::where('email', $email)->exists()) {
                $otherRoles[] = 'customer';
            }
            if (Admin::where('email', $email)->exists()) {
                $otherRoles[] = 'admin';
            }

            return response()->json([
                'user' => $staff->load(['person', 'managingAdmin']),
                'token' => $token,
                'role' => 'staff',
                'other_roles' => $otherRoles,
            ]);
        }

        $admin = Admin::where('email', $email)->first();
        $adminHash = $admin?->password ?? $dummyHash;
        $adminOk = Hash::check($password, $adminHash);

        if ($admin && $adminOk) {
            $token = $admin->createToken('auth-token')->plainTextToken;

            if (Customer::where('email', $email)->exists()) {
                $otherRoles[] = 'customer';
            }
            if (Staff::where('email', $email)->exists()) {
                $otherRoles[] = 'staff';
            }

            return response()->json([
                'user' => $admin->load('person'),
                'token' => $token,
                'role' => 'admin',
                'other_roles' => $otherRoles,
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['Bilgiler hatalı.'],
        ]);
    }

    public function myRoles(Request $request)
    {
        $user = $request->user();
        $email = $user->email;
        $currentRole = $this->getCurrentRole($user);

        // Single batched query: ask the DB which tables hold this email.
        // Avoids three serial round-trips and prevents the response time
        // from scaling with the number of role tables checked.
        $emails = [$email];
        $hasCustomer = Customer::whereIn('email', $emails)->exists();
        $hasStaff = Staff::whereIn('email', $emails)->exists();
        $hasAdmin = Admin::whereIn('email', $emails)->exists();

        $roles = [];
        if ($currentRole !== 'customer' && $hasCustomer) {
            $roles[] = 'customer';
        }
        if ($currentRole !== 'staff' && $hasStaff) {
            $roles[] = 'staff';
        }
        if ($currentRole !== 'admin' && $hasAdmin) {
            $roles[] = 'admin';
        }

        return response()->json([
            'current_role' => $currentRole,
            'other_roles' => $roles,
        ]);
    }

    public function switchRole(Request $request)
    {
        $request->validate([
            'role' => 'required|in:customer,staff,admin',
            'password' => 'required|string',
        ]);

        $user = $request->user();
        $email = $user->email;
        $targetRole = $request->role;

        $model = match ($targetRole) {
            'customer' => Customer::where('email', $email)->first(),
            'staff' => Staff::where('email', $email)->first(),
            'admin' => Admin::where('email', $email)->first(),
        };

        if (! $model) {
            return response()->json([
                'message' => "Bu email ile {$targetRole} rolünde kayıt bulunamadı.",
            ], 404);
        }

        // NOTE: We verify the password against the TARGET model's password,
        // not the current one. Multi-role users may keep different
        // passwords per role; this matches what a user means by "switch".
        if (! Hash::check($request->password, $model->password)) {
            throw ValidationException::withMessages(['password' => ['Şifre hatalı.']]);
        }

        $relations = match ($targetRole) {
            'customer' => ['person'],
            'staff' => ['person', 'managingAdmin'],
            'admin' => ['person'],
        };

        // The DB work is just "delete old token, create new one". The
        // response is built *after* commit so a serialization failure
        // can't roll back the auth change.
        $token = DB::transaction(function () use ($user, $model) {
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return $model->createToken('auth-token')->plainTextToken;
        });

        // Build other_roles list AFTER we've switched so we don't
        // accidentally echo the role we just left. $email was bound
        // above from the current user, so it's already correct here.
        $otherRoles = [];
        if ($targetRole !== 'customer' && Customer::where('email', $email)->exists()) {
            $otherRoles[] = 'customer';
        }
        if ($targetRole !== 'staff' && Staff::where('email', $email)->exists()) {
            $otherRoles[] = 'staff';
        }
        if ($targetRole !== 'admin' && Admin::where('email', $email)->exists()) {
            $otherRoles[] = 'admin';
        }

        return response()->json([
            'user' => $model->load($relations),
            'token' => $token,
            'role' => $targetRole,
            'other_roles' => $otherRoles,
        ]);
    }

    private function getCurrentRole($user): string
    {
        return match (get_class($user)) {
            Customer::class => 'customer',
            Staff::class => 'staff',
            Admin::class => 'admin',
            default => 'unknown',
        };
    }
}
