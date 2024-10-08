<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ResponseFormatter;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    public function request()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $check = \DB::table('password_reset_tokens')->where('email', request()->email)->count();
        if ($check > 0) {
            return ResponseFormatter::error(400, null, [
                'Anda sudah melakukan ini, silahkan resend OTP!'
            ]);

        }

        do {
            $otp = rand(100000, 999999);

            $otpCount = \DB::table('password_reset_tokens')->where('token', $otp)->count();
        } while ($otpCount > 0);

        \DB::table('password_reset_tokens')->insert([
            'email' => request()->email,
            'token' => $otp
        ]);

        $user = User::whereEmail(request()->email)->firstOrFail();
        \Mail::to($user->email)->send(new \App\Mail\SendForgotPasswordOTP($user, $otp));

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

        $otpRecord = \DB::table('password_reset_tokens')->where('email', request()->email)->first();
        if (is_null($otpRecord)) {
            return ResponseFormatter::error(400, null, [
                'Request tidak ditemukan'
            ]);
        }

        $user = User::whereEmail(request()->email)->firstOrFail();

        do {
            $otp = rand(100000, 999999);

            $otpCount = \DB::table('password_reset_tokens')->where('token', $otp)->count();
        } while ($otpCount > 0);

        \DB::table('password_reset_tokens')->where('email', request()->email)->update([
            'token' => $otp
        ]);

        \Mail::to($user->email)->send(new \App\Mail\SendForgotPasswordOTP($user, $otp));

        return ResponseFormatter::success([
            'is_sent' => true
        ]);
    }

    public function verifyOtp()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|exists:password_reset_tokens,token'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $check = \DB::table('password_reset_tokens')->where('token', request()->otp)->where('email', request()->email)->count();
        if ($check > 0) {
            return ResponseFormatter::success([
                'is_correct' => true
            ]);
        }
        
        return ResponseFormatter::error(400, 'Invalid OTP');
    }

    public function resetPassword()
    {
        $validator = \Validator::make(request()->all(), [
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|exists:password_reset_tokens,token',
            'password' => 'required|min:6|confirmed'
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $token = \DB::table('password_reset_tokens')->where('token', request()->otp)->where('email', request()->email)->first();
        if (!is_null($token)) {
            $user = User::whereEmail(request()->email)->first();
            $user->update([
                'password' => bcrypt(request()->password)
            ]);
            \DB::table('password_reset_tokens')->where('token', request()->otp)->where('email', request()->email)->delete();
            
            $token = $user->createToken(config('app.name'))->plainTextToken;

            return ResponseFormatter::success([
                'token' => $token
            ]); 
        }
        
        return ResponseFormatter::error(400, 'Invalid OTP');
    }
}
