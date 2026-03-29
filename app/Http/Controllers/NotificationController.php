<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = \App\Models\Notification::where('user_id', $request->user()->id)->latest()->paginate(20);
        return response()->json($notifications);
    }
}
