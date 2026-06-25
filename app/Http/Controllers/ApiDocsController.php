<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\WebhookEndpoint;

class ApiDocsController extends Controller
{
    public function index()
    {
        $webhooks = WebhookEndpoint::where('user_id', auth()->id())
            ->get(['id', 'url', 'secret', 'active', 'created_at']);

        return Inertia::render('Dashboard/ApiDocs', [
            'webhooks' => $webhooks,
        ]);
    }
}