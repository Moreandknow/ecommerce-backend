<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
        $categories = \App\Models\Category::whereNull('parent_id')->with(['childs'])->get();

        return ResponseFormatter::success($categories->pluck('api_response'));
    }

    public function getProduct()
    {
        $products = \App\Models\Product\Product::query();

        if (!is_null(request()->category)) {
            $category = \App\Models\Category::where('slug', request()->category)->firstOrFail();
            $products->where('category_id', $category->id);
        }

        if (!is_null(request()->seller)) {
            $seller = \App\Models\User::where('username', request()->seller)->firstOrFail();
            $products->where('seller_id', $seller->id);
        }

        if (!is_null(request()->search)) {
            $products->where('name', 'LIKE', '%' . request()->search . '%');
        }

        if (!is_null(request()->minimum_price)) {
            $products->whereRaw('IF(price_sale > 0, price_sale, price) >= ?', request()->minimum_price);
        }

        if (!is_null(request()->maximum_price)) {
            $products->whereRaw('IF(price_sale > 0, price_sale, price) <= ?', request()->maximum_price);
        }

        if (!is_null(request()->sorting_price)) {
            $type = request()->sorting_price == 'asc' ? 'ASC' : 'DESC';
            $products->orderByRaw('IF(price_sale > 0, price_sale, price) ' . $type);
        } else {
            $products->orderBy('id', 'desc');
        }

        if (!is_null(request()->categories) && is_array(request()->categories)) {
            $products->whereHas('category', function($subQuery){
                $subQuery->whereIn('slug', request()->categories);
            });
        }


        $products = $products->paginate(request()->per_page ?? 10);

        return ResponseFormatter::success($products->through(function($product){
            return $product->api_response_excerpt;
        }));
    }

    public function getProductDetail(string $slug)
    {
        $product = \App\Models\Product\Product::where('slug', $slug)->firstOrFail();

        return ResponseFormatter::success($product->api_response);
    }

    public function getProductReview(string $slug)
    {
        $product = \App\Models\Product\Product::where('slug', $slug)->firstOrFail();
        $reviews = $product->reviews();

        if (!is_null(request()->rating)) {
            $reviews = $reviews->where('star_seller', request()->rating);
        }

        if (!is_null(request()->with_attachment)) {
            $reviews = $reviews->whereNotNull('attachments');
        }

        if (!is_null(request()->with_description)) {
            $reviews = $reviews->whereNotNull('description');
        }

        $reviews = $reviews->paginate(request()->per_page ?? 10);

        return ResponseFormatter::success($reviews->through(function($review){
            return $review->api_response;
        }));
    }

    public function getSellerDetail(string $username)
    {
        $seller = \App\Models\User::where('username', $username)->firstOrFail();

        return ResponseFormatter::success($seller->api_response_as_seller);
    }

}
