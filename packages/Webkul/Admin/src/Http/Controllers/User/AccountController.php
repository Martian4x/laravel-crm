<?php

namespace Webkul\Admin\Http\Controllers\User;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;

class AccountController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return View
     */
    public function edit()
    {
        $user = auth()->guard('user')->user();

        return view('admin::user.account.edit', compact('user'));
    }

    /**
     * Update the account details (name, email, image, language).
     *
     * Password is handled separately on its own page so that updating a
     * profile detail no longer forces the user to re-enter their password.
     *
     * @return Response
     */
    public function update()
    {
        $user = auth()->guard('user')->user();

        $this->validate(request(), [
            'name'    => 'required',
            'email'   => 'email|unique:users,email,'.$user->id,
            'image.*' => 'nullable|mimes:bmp,jpeg,jpg,png,webp',
            'locale'  => 'nullable|in:'.implode(',', array_keys(config('app.available_locales'))),
        ]);

        $data = request()->only([
            'name',
            'email',
            'image',
            'locale',
        ]);

        if (isset($data['role_id']) || isset($data['view_permission'])) {
            session()->flash('warning', trans('admin::app.user.account.permission-denied'));

            return redirect()->back();
        }

        if (request()->hasFile('image')) {
            $data['image'] = current(request()->file('image'))->store('admins/'.$user->id);
        } else {
            if (! isset($data['image'])) {
                if (! empty($data['image'])) {
                    Storage::delete($user->image);
                }

                $data['image'] = null;
            } else {
                $data['image'] = $user->image;
            }
        }

        $user->update($data);

        session()->flash('success', trans('admin::app.account.edit.update-success'));

        return back();
    }

    /**
     * Show the change-password page.
     *
     * @return View
     */
    public function editPassword()
    {
        $user = auth()->guard('user')->user();

        return view('admin::user.account.password', compact('user'));
    }

    /**
     * Update the account password.
     *
     * @return Response
     */
    public function updatePassword()
    {
        $user = auth()->guard('user')->user();

        $this->validate(request(), [
            'current_password' => 'required|min:6',
            'password'         => 'required|min:6|confirmed',
        ]);

        if (! Hash::check(request('current_password'), $user->password)) {
            session()->flash('warning', trans('admin::app.account.edit.invalid-password'));

            return redirect()->back();
        }

        $user->update(['password' => bcrypt(request('password'))]);

        Event::dispatch('user.account.update-password', $user);

        session()->flash('success', trans('admin::app.account.edit.password-update-success'));

        return redirect()->route('admin.user.account.password.edit');
    }
}
