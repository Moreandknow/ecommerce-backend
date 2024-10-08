<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\ResponseFormatter;

class ProfileController extends Controller
{
    public function getProfile()
    {
        $user = auth()->user();

        return ResponseFormatter::success($user->api_response);
    }

    public function updateProfile()
    {
        $validator = \Validator::make(request()->all(), [
            'name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'photo' => 'nullable|image|max:1024',
            'username' => 'nullable|min:2|max:20',
            'phone' => 'nullable|numeric',
            'store_name' => 'nullable|min:2|max:100',
            'gender' => 'required|in:Laki-Laki,Perempuan.Lainnya',
            'birth_date' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return ResponseFormatter::error(400, $validator->errors());
        }

        $payload = $validator->validated();
        if (!is_null(request()->photo)) {
            $payload['photo'] = request()->file('photo')->store(
                'user-photo', 'public'
            );
        }

        auth()->user()->update($payload);

        return $this->getProfile();
    }

}
