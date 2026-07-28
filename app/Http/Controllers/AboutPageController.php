<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AboutPageController extends Controller
{
    public function show(Request $request): View
    {
        return view('about');
    }
}
