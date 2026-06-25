<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookEndpointController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->webhookEndpoints()->get(['id', 'url', 'secret', 'active', 'created_at'])
        );
    }

    public function store(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        $endpoint = $request->user()->webhookEndpoints()->create([
            'url' => $request->url,
            'secret' => Str::random(40),
        ]);

        return response()->json([
            'message' => 'Webhook registered',
            'id' => $endpoint->id,
            'secret' => $endpoint->secret,
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $request->user()->webhookEndpoints()->where('id', $id)->delete();
        return response()->json(['message' => 'Webhook deleted']);
    }
}
