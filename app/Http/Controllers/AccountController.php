<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function settings(Request $request)
    {
        return view('pages.account.settings', [
            'user' => $request->user(),
            'twoFactorEnabled' => (bool) $request->user()->twoFactorSetting?->enabled,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $emailChanged = $data['email'] !== $user->email;
        $user->name = $data['name'];
        $user->email = $data['email'];
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        return redirect()->route('account.settings')->with('status', $emailChanged
            ? '账号信息已保存，请重新验证新的邮箱地址。'
            : '账号信息已保存。');
    }
}
