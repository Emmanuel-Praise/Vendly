<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ApiResponse;

class ChatController extends Controller
{
    use ApiResponse;
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message_text' => 'nullable|string|max:1000',
            'media_file' => 'nullable|file|mimes:jpeg,png,jpg,mp4|max:10000',
            'audio_file' => 'nullable|file|mimes:mp3,m4a,aac,wav|max:5000',
        ]);

        $message = \App\Models\Message::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $request->receiver_id,
            'message_text' => $request->message_text,
        ]);

        if ($request->hasFile('media_file')) {
            if (env('CLOUDINARY_URL') && class_exists('\CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary')) {
                $message->media_url = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::upload($request->file('media_file')->getRealPath(), ['resource_type' => 'auto'])->getSecurePath();
            } else {
                $path = $request->file('media_file')->store('chat_media', 'public');
                $message->media_url = asset('storage/' . $path);
            }
        }

        if ($request->hasFile('audio_file')) {
            if (env('CLOUDINARY_URL') && class_exists('\CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary')) {
                $message->audio_url = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::upload($request->file('audio_file')->getRealPath(), ['resource_type' => 'auto'])->getSecurePath();
            } else {
                $path = $request->file('audio_file')->store('chat_audio', 'public');
                $message->audio_url = asset('storage/' . $path);
            }
        }

        $message->save();

        \App\Models\Conversation::firstOrCreate([
            'user_one' => min($request->user()->id, $request->receiver_id),
            'user_two' => max($request->user()->id, $request->receiver_id),
        ]);

        return $this->success($message, 'Message sent', 201);
    }

    public function getMessages(Request $request, $userId)
    {
        $myId = $request->user()->id;
        
        $conversation = \App\Models\Conversation::where(function($q) use ($myId, $userId) {
            $q->where('user_one', min($myId, $userId))->where('user_two', max($myId, $userId));
        })->first();

        if (!$conversation) {
            return $this->error('Conversation not found', 403);
        }

        $messages = \App\Models\Message::where(function($q) use ($myId, $userId) {
            $q->where('sender_id', $myId)->where('receiver_id', $userId);
        })->orWhere(function($q) use ($myId, $userId) {
            $q->where('sender_id', $userId)->where('receiver_id', $myId);
        })->orderBy('created_at', 'asc')->paginate(50);

        return $this->success($messages);
    }

    public function getConversations(Request $request)
    {
        $myId = $request->user()->id;
        $conversations = \App\Models\Conversation::with(['userOne', 'userTwo'])
            ->where(function($q) use ($myId) {
                $q->where('user_one', $myId)
                  ->orWhere('user_two', $myId);
            })
            ->paginate(20);

        return $this->success($conversations);
    }

    public function findUserByPhone(Request $request, $phone)
    {
        $user = \App\Models\User::where('phone', $phone)->first();
        if (!$user) {
            return $this->error('User not found', 404);
        }
        return $this->success($user);
    }
}
