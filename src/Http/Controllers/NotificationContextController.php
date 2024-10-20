<?php

namespace ChijiokeIbekwe\Raven\Http\Controllers;

use Illuminate\Http\JsonResponse;
use ChijiokeIbekwe\Raven\Models\NotificationContext;

class NotificationContextController extends Controller
{
    

    public function index(): JsonResponse {

        if (!auth()->check()) {
            return new JsonResponse([
                'status' => 'failure',
                'message' => 'You are not authorized to access this API'
            ], 401);
        }

        $contexts = NotificationContext::all();

        return new JsonResponse([
            'status' => 'success',
            'message' => 'Notification contexts retrieved successfully',
            'data' => $contexts
        ], 200);
    }
}