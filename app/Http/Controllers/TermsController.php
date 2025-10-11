<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use function Termwind\render;

class TermsController extends Controller
{
    public function index() 
    {
        return Inertia('Dashboard/Terms');
    }
}
