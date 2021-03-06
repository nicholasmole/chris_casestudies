<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UpdatePasswordRequest;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use \Sentinel;
use \Auth;
use \Route;

use App\Notification;
use App\User;
use App\Study;
use App\Helpers\Helpers;

class AdminController extends Controller
{

    public function __construct(){
        // check to see if there's learning outcomes, courses or case studies in the DB.
        // If not, suggest creating them.

        $this->middleware('authorize');
    }

    /**
     * Show the home dashboard page.
     *
     * @return  \Illuminate\Http\Response
     */
    public function index()
    {
        $team = User::all();

        return view('layouts.admin.dashboard')->with('team', $team);
    }


    /**
     * Show the profile for the logged in user.
     *
     * @return  \Illuminate\Http\Response
     */
    public function profile()
    {
        $user = Auth::user();
        $studies = Study::where('user_id', $user->id)->get()->all();

        return view('layouts.admin.profile.index')->with([
            'user'    => $user,
            'studies' => $studies
        ]);
    }


    /**
     * Show the profile for a specified user.
     *
     * @return Illuminate\Http\Response
     */
    public function userProfile($id)
    {
        if(Auth::user()->id == $id) {
            return redirect(route('admin.profile'));
        }

        $user = User::FindOrFail($id);
        $studies = $user->studies()->get()->all();

        return view('layouts.admin.profile.profile')->with([
            'user'    => $user,
            'studies' => $studies
        ]);
    }


    /**
     * Change the user's password.
     *
     *
     * @return Response
     */
    public function password()
    {
        return view('layouts.admin.profile.password');
    }


    /**
     * Change the user's password.
     *
     *
     * @return Response
     */
    public function updatePassword(UpdatePasswordRequest $UpdatePasswordRequest)
    {
        $user = Auth::user();

        if (Auth::attempt(['email' => $user->email, 'password' => $UpdatePasswordRequest->old_password])) {
            $user->password = $UpdatePasswordRequest->password;
            $user->save();

            Helpers::flash('Your password has been successfully updated.');
            return redirect(route('admin.profile'));

        } else {
            return redirect(route('admin.profile.password'))->withErrors(['The old password entered was incorrect.']);
        }
    }


    /**
     * Get a users notifications.
     *
     * @return Illuminate\Http\Response
     */
    public function notifications()
    {
        $notifications = Auth::user()->notifications()->with(['study' => function($query){
            $query->withTrashed();
        }])->get();

        return view('layouts.admin.notifications.manage')->with('notifications', $notifications);
    }


    /**
     * Detach notifications from a user.
     *
     * @return  null
     */
    public function destroyNotification($id)
    {

        $notifications = Notification::find(explode(',', $id));
        Auth::user()->notifications()->detach($notifications->lists('id')->toArray());

        if($notifications->count() > 1) {
            Helpers::flash('The notifications have been successfully deleted.');
        } else {
            Helpers::flash('The notificatio has been successfully deleted.');
        }

        return redirect(route('admin.notifications'));
    }

}
