<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MessagePageController extends Controller
{
    public function messages(Request $request)
    {
        return view('pages.messages.index', [
            'currentUserId' => $request->user()?->id,
            'api' => [
                'users' => url('/api/messages/users'),
                'index' => url('/api/messages'),
                'store' => url('/api/messages'),
                'show' => url('/api/messages'),
                'read' => url('/api/messages'),
                'stream' => url('/api/messages'),
            ],
        ]);
    }

    public function groups(Request $request)
    {
        return view('pages.groups.index', [
            'currentUserId' => $request->user()?->id,
            'api' => [
                'users' => url('/api/messages/users'),
                'index' => url('/api/groups'),
                'store' => url('/api/groups'),
                'show' => url('/api/groups'),
                'addMember' => url('/api/groups'),
                'removeMember' => url('/api/groups'),
                'sendMessage' => url('/api/groups'),
                'read' => url('/api/groups'),
                'stream' => url('/api/groups'),
            ],
        ]);
    }
}
