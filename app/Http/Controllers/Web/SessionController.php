<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function create()
    {
        return view('auth.session', ['register' => false]);
    }

    public function register()
    {
        return view('auth.session', ['register' => true]);
    }

    public function store(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:72']]);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'Email yoki parol noto‘g‘ri.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('cabinet'));
    }

    public function saveRegistration(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'between:2,120'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(8)->letters()->numbers()],
        ]);
        Auth::login(User::create($data));
        $request->session()->regenerate();

        return redirect()->route('cabinet');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
