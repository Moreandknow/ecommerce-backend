<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ResponseFormatter;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AuthenticationController extends Controller
{
    public function authGoogle()
    {
        $validator = \Validator::make(request()->all(), [
            'token' => 'required'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken(request()->token);
        if ($payload) {
            $userId = $payload['sub'];
            $name = $payload['name'];
            $email = $payload['email'];

            $user = User::where('social_media_provider', 'google')->where('social_media_provider', $userId)->first();
            if (!is_null($user)) {
                $token = $user->createToken(config('app.name'))->plainTextToken;

                return ResponseFormatter::success([
                    'token' => $token
                ]);
            }

            $user = User::where('email', $email)->first();
            if (!is_null($user)) {
                $user->update([
                    'social_media_provider' => 'google',
                    'social_media_id' => $userId
                ]);
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'social_media_provider' => 'google',
                    'social_media_id' => $userId
                ]);
            }

            $token = $user->createToken(config('app.name'))->plainTextToken;

            return ResponseFormatter::success([
                'token' => $token
            ]);
            

        } else {
            return ResponseFormatter::error(400, null, [
                'Invalid token'
            ]);
        }
    }

    public function authFacebook()
    {
        $validator = \Validator::make(request()->all(), [
            'token' => 'required|string'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $accessToken = request()->token;
        $response = Http::get('https://graph.facebook.com/v19.0/me', [ // Baris ini yang tadi error
            'fields' => 'id,name,email',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            return ResponseFormatter::error(400, null, ['Invalid Facebook token']);
        }

        $fbUser = $response->json();

        if (empty($fbUser['email'])) {
            return ResponseFormatter::error(400, null, ['Facebook email permission is required.']);
        }

        try {
            $userId = $fbUser['id'];
            $name = $fbUser['name'];
            $email = $fbUser['email'];

            $user = User::where('social_media_provider', 'facebook')->where('social_media_id', $userId)->first();
            if (!is_null($user)) {
                $token = $user->createToken(config('app.name'))->plainTextToken;
                return ResponseFormatter::success(['token' => $token]);
            }

            $user = User::where('email', $email)->first();
            if (!is_null($user)) {
                $user->update([
                    'social_media_provider' => 'facebook',
                    'social_media_id' => $userId
                ]);
            } else {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'social_media_provider' => 'facebook',
                    'social_media_id' => $userId,
                    'email_verified_at' => now()
                ]);
            }

            $token = $user->createToken(config('app.name'))->plainTextToken;

            return ResponseFormatter::success(['token' => $token]);

        } catch (\Exception $e) {
            return ResponseFormatter::error(500, null, ['An error occurred: ' . $e->getMessage()]);
        }
    }

    public function register()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|unique:users,email'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        do {
            $otp = rand(100000, 999999);

            $otpCount = User::where('otp_register', $otp)->count();
        } while ($otpCount > 0);

        $user = User::create([
            'email' => request()->email,
            'name' => request()->email,
            'otp_register' => $otp
        ]);

        \Mail::to($user->email)->send(new \App\Mail\SendRegisterOTP($user));

        return ResponseFormatter::success([
            'is_sent' => true
        ]);
    }

    public function resendOtp()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $user = User::where('email', request()->email)->whereNotNull('otp_register')->first();
        if (is_null($user)) {
            return ResponseFormatter::error(400, null, [
                'User tidak ditemukan'
            ]);
        }

        do {
            $otp = rand(100000, 999999);

            $otpCount = User::where('otp_register', $otp)->count();
        } while ($otpCount > 0);

        $user->update([
            'otp_register' => $otp
        ]);

        \Mail::to($user->email)->send(new \App\Mail\SendRegisterOTP($user));

        return ResponseFormatter::success([
            'is_sent' => true
        ]);
    }

    public function verifyOtp()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|exists:users,otp_register'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $user = User::where('email', request()->email)->where('otp_register', request()->otp)->count();
        if ($user > 0) {
            return ResponseFormatter::success([
                'is_correct' => true
            ]);
        }
        
        return ResponseFormatter::error(400, 'Invalid OTP');
    }

    public function verifyRegister()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|exists:users,otp_register',
            'password' => 'required|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $user = User::where('email', request()->email)->where('otp_register', request()->otp)->first();
        if (!is_null($user)) {
            $user->update([
                'otp_register' => null,
                'email_verified_at' => now(), 
                'password' => bcrypt(request()->password)
            ]);
            
            $token = $user->createToken(config('app.name'))->plainTextToken;

            return ResponseFormatter::success([
                'token' => $token
            ]); 
        }
        
        return ResponseFormatter::error(400, 'Invalid OTP');
    }

    public function  login()
    {
        $validator = \Validator::make(request()->all(), [
            'phone_email' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $user = User::where('email', request()->phone_email)->orWhere('phone', request()->phone_email )->first();
        if (is_null($user)) {
            return ResponseFormatter::error(400, null, [
                'User tidak ditemukan'
            ]);
        }

        $userPassword = $user->password;
        if (\Hash::check(request()->password, $userPassword)) {
            $token = $user->createToken(config('app.name'))->plainTextToken;

            return ResponseFormatter::success([
                'token' => $token
            ]);
        }
        
        return ResponseFormatter::error(400, null, [
            'Password salah'
        ]);
    }
}
