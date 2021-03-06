<?php

namespace App\Modules\Users\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\User;

use Auth;
use Hash;
use Theme;
use Settings;
use Cache;

class UsersController extends Controller
{
    /**
    * Login
    *
    * @param Request $request
    *
    * @return mixed
    */
    public function login(Request $request)
    {
        $throttling_key = 'login_attempt-' . $request->ip();
        $throttling_time_key = 'login_attempt_time-' . $request->ip();

        $attempts = Cache::get($throttling_key, 0);
        $last_attempt = Cache::get($throttling_time_key, time());

        if ($attempts >= Settings::get('login_throttling_limit')) {
            $time = (Settings::get('login_throttling_minutes') * 60);

            if (time() - $last_attempt < $time) {
                abort(404, 'You cannot login yet.');
            }
        }

        if ($request->getMethod() == 'POST') {
            $data = [
                'username' => $request->get('username'),
                'password' => $request->get('password')
            ];

            if (Auth::attempt($data)) {
                // Update Last Logged In
                $user = User::where('id', '=', Auth::user()->id)->first();
                $user->updateLastLoggedIn();

                return redirect()->route($user->getRedirectTo())->with('success', 'You have been logged in.');
            } else {
                $existing_attempts = Cache::get($throttling_key, 0);

                if ($existing_attempts) {
                    $existing_attempts++;
                } else {
                    $existing_attempts = 1;
                }

                Cache::forever($throttling_key, $existing_attempts);
                Cache::forever($throttling_time_key, time());

                $request->session()->flash('error', 'Incorrect account information entered. Please try again.');
            }
        }

        $theme = Theme::uses(Cache::get('theme', 'default'))->layout('simple');
        
        $theme->setTitle('Login');

        return $theme->scope('users.login')->render();
    }

    /**
    * Register
    *
    * @param Request $request
    *
    * @return mixed
    */
    public function register(Request $request)
    {
        if (Settings::get('enable_user_signups') == 0) {
            return redirect()->route('home')->with('error', 'Sorry, users cannot sign up at this time.');
        }

        if ($request->getMethod() == 'POST') {
            if ($request->get(env('APP_KEY') . '_token')) {
                $request->session()->flash('error', 'Incorrect account information entered. Please try again.');
            } else {
                $user = new User;

                $user->add($request->except('_token'));

                if (Auth::loginUsingId($user->id)) {
                    // Update Last Logged In
                    $user = User::find($user->id);
                    $user->updateLastLoggedIn();

                    return redirect()->route('home')->with('success', 'You have signed up.');
                } else {
                    $request->session()->flash('error', 'Incorrect account information entered. Please try again.');
                }
            }
        }
        
        $this->theme->setTitle('Register');

        return $this->theme->scope('users.register')->render();
    }

    /**
    * Logout
    *
    * @return Redirect
    */
    public function logout()
    {
        if (Auth::check()) {
            Auth::logout();

            return redirect()->route('home')->with('success', 'You have been logged out.');
        } else {
            return redirect()->route('home')->with('error', 'You cannot logout without being logged in first.');
        }
    }

    /**
    * Dashboard
    *
    * @return View
    */
    public function dashboard()
    {
        return view('users::Users/dashboard');
    }

    /**
    * Profile Edit
    *
    * @param Request $request
    *
    * @return mixed
    */
    public function profileEdit(Request $request)
    {
        $model = User::find(Auth::user()->id);

        $errors = [];
        if ($request->getMethod() == 'POST') {
            $validator = Validator::make($request->all(), [
                'username' => [
                      'required',
                      Rule::unique('users')->ignore($model->id)
                ],
                'email' => [
                      'required',
                      Rule::unique('users')->ignore($model->id)
                ],
                'password' => 'confirmed',
                'first_name' => 'required',
                'last_name' => 'required'
            ]);

            if (!$validator->fails()) {
                if (!$request->get('password')) {
                    unset($model->password);
                }

                $model->edit($request->except([ '_token' ]));

                return redirect()->route('dashboard')->with('success', 'Your profile has been saved.');
            } else {
                $errors = $validator->errors()->getMessages();
            }
        }
        
        $this->theme->setTitle('Edit Profile');

        return $this->theme->scope('users.profile_edit', compact('model', 'errors'))->render();
    }

    /**
    * Profile
    *
    * @param Request $request
    * @param string $username
    *
    * @return View
    */
    public function profile(Request $request, $username)
    {
        $user = User::where('username', '=', $username)->first();

        if (empty($user)) {
            abort(404, 'Cannot find user.');
        }
        
        $this->theme->setTitle('Profile - ' . $username);

        return $this->theme->scope('users.profile', compact('user'))->render();
    }
}
