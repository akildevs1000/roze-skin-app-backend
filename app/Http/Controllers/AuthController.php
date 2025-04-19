<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();


        //check user status
        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Your account is currently marked as inactive.'],
            ]);
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return response()->json([
            'token' => $user->createToken('myApp')->plainTextToken,
            'user' => $user,
        ], 200);
    }
    public function CompanyLogin(Request $request)
    {
        $model = User::query();
        $user = $model->whereEmail($request->email)->with('company', 'employee')->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // if (!$user || $user->company->expiry <= now()) {
        //     throw ValidationException::withMessages([
        //         'email' => ['Your account Expired'],
        //     ]);
        // }

        return response()->json([
            'token' => $user->createToken('myApp')->plainTextToken,
            'user' => $model->first(),
        ], 200);
    }
    public function me(Request $request)
    {
        $user = User::where('email', $request->user()->email)->first();
       
        return response()->json(['user' => $user], 200);

        // return response()->json(['user' => $user], 200);

        // $user = Auth::user();
        // return response()->json([
        //     'user' => $user,
        //     'permissions' => [],
        // ], 200);
    }

    public function logout(Request $request)
    {
        $user = User::find($request->user()->id);
        $user->is_verified = 0;
        $user->save();
        $request->user()->tokens()->delete();
    }

    public function generateOTP(Request $request, $userId)
    {
        try {
            $random_number = mt_rand(100000, 999999);
            $user = User::with('company')->find($userId);
            $user->otp = $random_number;

            if ($user->save()) {
                if (app()->isProduction()) {
                    (new WhatsappNotificationController())->loginOTP($user);
                }
                return $this->response('updated.', null, true);
            }
        } catch (\Throwable $th) {
            return $this->response($th, null, true);
        }
    }

    public function checkOTP(Request $request, $otp)
    {
        try {
            $user = User::with('company')->find($request->userId);
            if ($user->otp == $otp) {
                $user->is_verified = 1;
                $user->save();
                return $this->response('updated.', $user, true);
            }
            $user->is_verified = 0;
            $user->save();
            return $this->response('updated.', null, false);
        } catch (\Throwable $th) {
            return $this->response($th, null, false);
        }
    }
}
