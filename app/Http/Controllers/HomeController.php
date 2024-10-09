<?php

namespace App\Http\Controllers;

use App\ResponseFormatter;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function getSlider()
    {
        $sliders = \App\Models\Slider::all();

        return ResponseFormatter::success($sliders->pluck('api_response'));
    }

    public function getCategory()
    {
        $categories = \App\Models\Category::whereNull('parent_id')->with('childs')->get();

        return ResponseFormatter::success($categories->pluck('api_response'));
    }
}
