<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $userCount = User::count();
        $productCount = Product::count();
        return [
            'userCount' => $userCount,
            'productCount' => $productCount,
        ];
    }
}
