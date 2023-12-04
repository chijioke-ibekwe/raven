<?php

namespace ChijiokeIbekwe\Messenger\Http\Controllers;

use Illuminate\Http\JsonResponse;
use ChijiokeIbekwe\Messenger\Models\NotificationContext;

class NotificationContextController extends Controller
{

    public function index(): JsonResponse {

        if (!auth()->check()) {
            return new JsonResponse([
                'status' => false,
                'msg' => 'You are not authorized to access this API'
            ], 401);
        }

        $contexts = NotificationContext::with('notification_channels')->get();

        foreach ($contexts as $context){
            foreach ($context->notification_channels as $channel){
                unset($channel['pivot']);
            }
        }

        return new JsonResponse([
            'status' => true,
            'msg' => 'Success',
            'data' => $contexts
        ], 200);
    }
}