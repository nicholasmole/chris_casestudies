<?php

namespace App\Http\Controllers;

use Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Http\Requests\StoreOutcomeRequest;
use App\Http\Requests\UpdateOutcomeRequest;

use App\Helpers\Helpers;

use App\Outcome;
use App\Course;

class OutcomesController extends Controller
{

    /**
     * Show all of the outcomes.
     *
     * @return Illuminate\Http\Response
     */
    public function index()
    {
        $outcomes = Outcome::latest()->get()->all();

        return view('layouts.admin.outcomes.manage')->with('outcomes', $outcomes);
    }


    /**
     * Create a new outcome.
     *
     * @return Illuminate\Http\Response
     */
    public function create()
    {
        $courses = Course::latest()->get()->all();

        return view('layouts.admin.outcomes.create');
    }


    /**
     * Store an outcome.
     *
     * @return Response
     */
    public function store(StoreOutcomeRequest $StoreOutcomeRequest)
    {
        // @TODO: user authorization

        Outcome::create($StoreOutcomeRequest->all());
        Helpers::flash('The learning outcome has been successfully added.');

        return redirect(route('admin.outcomes.index'));
    }


    /**
     * Update an outcome.
     *
     * @return Response
     */
    public function update(UpdateOutcomeRequest $UpdateOutcomeRequest, $id)
    {
        // @TODO: user authorization

        $outcome = Outcome::findOrFail($id);
        $outcome->update($UpdateOutcomeRequest->all());

        Helpers::flash('The learning outcome has been successfully updated');

        return redirect(route('admin.outcomes.index'));
    }


    /**
     * Delete an outcome.
     *
     * @return null
     */
    public function destroy($id)
    {
        // @TODO: user authorization
        //
        // @TODO: when an outcome is deleted, detach associated studies and courses.

        $outcome = Outcome::findOrFail($id);
        $outcome->delete();

        Helpers::flash('The learning outcome has been successfully deleted.');
        return redirect(route('admin.outcomes.index'));
    }


    /**
     * Show an outcome.
     *
     * @return json
     */
    public function show($id)
    {
        $outcome = Outcome::findOrFail($id);

        if(Request::ajax()) {
            return $outcome;
        } else {
            // url was entered manually, user is probably trying to edit.
            return redirect(route('admin.outcomes.edit', $id));
        }
    }


    /**
     * Edit an outcome.
     *
     * @return Illuminate\Http\Response
     */
    public function edit($id)
    {
        // @TODO: user authorization

        $outcome = Outcome::findOrFail($id);
        $courses = Course::latest()->get()->all();

        return view('layouts.admin.outcomes.edit')->with('outcome', $outcome)->with('courses', $courses);
    }

}
