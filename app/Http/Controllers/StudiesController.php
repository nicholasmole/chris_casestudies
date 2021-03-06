<?php

namespace App\Http\Controllers;

use Request;

use App\Http\Requests;
use App\Http\Requests\StoreStudyRequest;
use App\Http\Requests\UpdateStudyRequest;

use App\Http\Controllers\Controller;
use App\Helpers\Helpers;
use \Auth;
use \Sentinel;
use \Session;

use App\Study;
use App\Keyword;
use App\User;
use App\Notification;
use App\Outcome;

// @TODO: Eager loading queries -- get rid of queries in loops.

class StudiesController extends Controller
{

    /**
    * Show all of the case studies.
    *
    * @return \Illuminate\Http\Response
    */
    public function index()
    {
        // check if user has permission to access this page.
        if($this->checkAccess()) {

            $studies = Study::where('draft', false)->with('user')->latest()->get();

            return view('layouts.admin.cases.manage')->with('studies', $studies);

        } else {

            return redirect(route('admin'))->withErrors('You do not have permission to access that location.');

        }
    }


    /**
    * Create a new case study.
    *
    * @return \Illuminate\Http\Response
    */
    public function create()
    {
        $outcomes = Outcome::latest()->get()->all();

        return view('layouts.admin.cases.create')->with('outcomes', $outcomes);
    }


    /**
    * Store a new case study.
    *
    * @param StoreDraftRequest $StoreDraftRequest
    * @return Response
    */
    public function store(StoreStudyRequest $StoreStudyRequest, Study $study)
    {

        if($StoreStudyRequest->has('publish')) {

            $user = Sentinel::findById(Auth::user()->id);

            // check if user has permissions to publish.
            if($user->hasAccess(['publish'])) {

                // user is authorized to publish
                $this->saveStudy($study, $StoreStudyRequest->all(), false);

                return redirect(route('admin.cases.index'));

            } else {

                //user is not authorized to publish. Flash request to session and redirect with error.
                return redirect(route('admin.cases.create'))->withErrors('You do not have permission to publish.')->withInput($StoreStudyRequest->all());

            }

        } else {

            // must be a draft if not publish. request validation will have
            // already determined it must be either publish or draft.
            $this->saveStudy($study, $StoreStudyRequest->all(), true);

            return redirect(route('admin.cases.drafts'));

        }

    }


    /**
     * Update a case study.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateStudyRequest $UpdateStudyRequest, $slug)
    {
        $user = Sentinel::findById(Auth::user()->id);
        $study = Study::where('slug', $slug)->firstOrFail();
        $input = $UpdateStudyRequest->all();

        // @TODO: refactor to switch

        if($UpdateStudyRequest->has('publish-draft')) {
            // check if user has permissions to publish.
            if($user->hasAccess(['publish'])) {
                // user has permission to publish

                $this->saveStudy($study, $input, false);
                return redirect(route('admin.cases.index'));

            } else {
                //user doesn't have permission to publish
                return redirect(route('admin.cases.edit'))->withErrors('You do not have permission to publish.')->withInput($UpdateStudyRequest->all());
            }

        } else if($UpdateStudyRequest->has('update-draft') || $UpdateStudyRequest->has('redraft')) {
            // update a draft or revert a published one to a draft.
            $this->saveStudy($study, $input, true);
            return redirect(route('admin.cases.drafts'));

        } else {
            // UpdateStudyRequest->has('update')
            // updating a published study

            if($user->hasAccess(['publish'])) {

                $this->saveStudy($study, $input, false);

                return redirect(route('admin.cases.index'));

            } else {
                //user doesn't have permission to update a pubished case study.
                 return redirect(route('admin.cases.edit', $slug))->withErrors('You do not have permission to update a published study.')->withInput($UpdateStudyRequest->all());

            }

        }

    }


    /**
     * Edit a case study
     *
     * @return \Illuminate\Http\Response
     */
    public function edit($slug)
    {

        $study = Study::where('slug', $slug)->firstOrFail();
        $keywords = $this->stringifyKeywords($study->keywords()->get());
        $outcomes = Outcome::latest()->get()->all();

        if($study->draft) {
        // any user can edit a draft, no permissions check.
            return view('layouts.admin.cases.edit')->with('study', $study)->with([
                'keywords' => $keywords,
                'outcomes' => $outcomes
            ]);
        } else {
        // it's not a draft, make sure the user has permission to edit non-drafts.
            if(Sentinel::findById(Auth::user()->id)->hasAccess(['publish'])) {
            // user can edit published studies
                return view('layouts.admin.cases.edit')->with([
                    'study'    => $study,
                    'keywords' => $keywords,
                    'outcomes' => $outcomes
                ]);
            } else {
            // user cannot edit published studies.
                return redirect(route('admin.cases.drafts'))->withErrors('You do not have permission to edit a published study.');
            }
        }
    }


    /**
     * soft delete a case study.
     *
     * @return  \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $studies = Study::find(explode(',', $id));

        if(!$studies->where('draft', false)->isEmpty()) {
            // published studies in collection
            if(Sentinel::findById(Auth::user()->id)->hasAccess(['publish'])) {
            // user can delete
                Study::destroy($studies->lists('id')->toArray());

                if($studies->count() > 1) {
                    Helpers::flash('The case studies have been deleted.');
                } else {
                    Helpers::flash('The case study has been deleted.');
                }
                return redirect(route('admin.cases.index'));
            } else {
                return redirect(route('admin.cases.drafts'))->withErrors('You do not have permission to delete case studies.');
            }

        } else {
            // only drafts in collection
            Study::destroy($studies->lists('id')->toArray());

            if($studies->count() > 1) {
                Helpers::flash('The drafts have been deleted.');
            } else {
                Helpers::flash('The draft has been deleted.');
            }

            return redirect(route('admin.cases.drafts'));
        }
    }


    /**
     * Permanently delete case studies from the trash.
     *
     * @param  Request
     * @return  \Illuminate\Http\Response
     */
    public function forceDestroy($id)
    {
        $studies = Study::onlyTrashed()->find(explode(',', $id));

        $studies->each(function($study){
            $study->forceDelete();
        });

        if($studies->count() > 1) {
            Helpers::flash('The case studies have been permanently deleted.');
        } else {
            Helpers::flash('The case study has been permanently deleted.');
        }

        return redirect(route('admin.cases.trash'));
    }


    /**
     * Restore a soft deleted study to a draft.
     *
     * @return \Illuminate\Http\Response
     */
    public function restore($id)
    {

        $studies = Study::withTrashed()->find(explode(',', $id));

        $studies->each(function($study){
            $study->draft = true;
            $study->save();
            $study->restore();
        });

        if($studies->count() > 1) {
            Helpers::flash('The case studies have been restored.');
        } else {
            Helpers::flash('The case study has been restored.');
        }

        return redirect(route('admin.cases.drafts'));
    }


    /**
     * Respond to an AJAX request with a study.
     *
     * @return json
     */
    public function show($slug)
    {
        $study = Study::withTrashed()->where('slug', $slug)->firstOrFail();
        $keywords = $study->keywords()->get();

        if(Request::ajax()) {
            return array('study' => $study, 'keywords' => $keywords);
        } else {
            // url was entered manually, user is probably trying to edit.
            return redirect(route('admin.cases.edit', $slug));
        }
    }


    /**
    * Show all the drafts.
    *
    * @return  \Illuminate\Http\Response
    */
    public function drafts()
    {
        $drafts = Study::where('draft', true)->with('user')->latest()->get();

        return view('layouts.admin.cases.drafts')->with('drafts', $drafts);

    }


    /**
    * Show all trashed studies.
    *
    * @return  \Illuminate\Http\Response
    */
    public function trash()
    {
        $studies = Study::onlyTrashed()->get();

        return view('layouts.admin.cases.trash')->with('studies', $studies);
    }


    /**
     * Show settings page for case studies.
     *
     * @return \Illuminate\Http\Response
     */
    public function settings()
    {
        return view('layouts.admin.settings.studies');
    }


    /**
     * Regenerate all case studies slugs from their titles.
     *
     * @return
     */
    public function resetURLs()
    {
        $studies = Study::all();

        $studies = $studies->each(function($study){
            $study->slug = $this->slugify($study->title);
            $study->save();
        });

        Helpers::flash('URLs have been reset for all case studies.');
        return redirect(route('admin.settings.studies'));
    }


    /**
     * Save a case study.
     *
     * @param  object $study
     * @param  array $input
     * @param  bool $isDraft
     * @return null
     */
    private function saveStudy($study, $input, $isDraft)
    {
        $study->title              = $input['title'];
        $study->problem            = $input['problem'];
        $study->solution           = $input['solution'];
        $study->analysis           = $input['analysis'];
        $study->excerpt            = $this->makeExcerpt($study->problem, 500);
        $study->schedule_impact    = $input['schedule_impact'];
        $study->budget_impact      = $input['budget_impact'];
        $study->delivery_method    = $input['delivery_method'];
        $study->estimated_schedule = $input['estimated_schedule'];
        $study->contract_value     = $input['contract_value'];
        $study->market_sector      = $input['market_sector'];
        $study->topic              = $input['topic'];
        $study->location           = $input['location'];
        $study->draft              = $isDraft;

        if(empty($input['slug'])) {
            $slug = $this->slugify($study->title);


            if(Study::where('slug', $slug)->first()) {
                $slug = $slug.'-new';
            }

            $study->slug = $slug;

        } else {
            $study->slug = $this->slugify($input['slug']);
        }

        Auth::user()->studies()->save($study);

        $this->syncKeywords($study, $this->storeKeywords($input['keywords']));

        if(Request::has('outcomes')) {
            $this->syncOutcomes($study, $input['outcomes']);
        } else {
            $study->outcomes()->detach();
        }

        $notification = new Notification;

        switch (Request::input()):

            case Request::has('publish'):
            //add new published study
                Helpers::flash('The case study has been published.');
                $notification->notification = "A new case study has been published.";
            break;

            case Request::has('draft'):
            //add New Draft
                Helpers::flash('The draft has been added.');
                $notification->notification = "A new draft has been added.";
            break;

            case Request::has('publish-draft'):
            //publish a draft
                Helpers::flash('The draft has been published.');
                $notification->notification = "A draft has been published.";
            break;

            case Request::has('update'):
            //update published Study
                Helpers::flash('The case study has been updated.');
                $notification->notification = "A case study has been updated.";
            break;

            case Request::has('update-draft'):
            //update draft
                Helpers::flash('The draft has been updated.');
                $notification->notification = "A draft has been updated.";
            break;

        endswitch;

        $this->notifier($notification, $study, Sentinel::findRoleBySlug('admin')->users()->get());
    }


    /**
    * Check if the keyword already exists in the DB and build up
    * an array of ID's to be attached to a study.
    *
    * @param array $keywords
    * @return null
    */
    private function storeKeywords($keywords)
    {
        // explode string at commas and spaces
        $keywords = array_map('trim', preg_split('~[\s,]+~', $keywords));

        $keywordIds = [];
        foreach($keywords as $keyword) {

            if(Keyword::where('name', $keyword)->first()) {

                array_push($keywordIds, Keyword::where('name', $keyword)->first()->id);

            } else {

                $k = new Keyword;
                $k->name = $keyword;
                $k->save();
                $lastInsertId = $k->id;

                array_push($keywordIds, $lastInsertId);
            }
        }

        return array_map('intval', $keywordIds);
    }


    /**
     * Sync learning outcomes with a study.
     *
     * @param Study $study
     * @param array $outcomes
     */

    private function syncOutcomes(Study $study, array $outcomes)
    {
        $study->outcomes()->sync($outcomes);
    }


    /**
     * Sync keywords with a study.
     *
     * @param  Study  $study
     * @param  array  $keywords
     */
    private function syncKeywords(Study $study, array $keywords)
    {
        $study->keywords()->sync($keywords);
    }


    /**
    * Stringifies a collection of keywords.
    *
    * @param  object $keywords
    * @return string
    */
    private function stringifyKeywords($keywords)
    {
        $keywordString = "";

        if($keywords) {

            foreach($keywords as $keyword) {
                $keywordString = $keywordString . $keyword->name . ', ';
            }

            return trim($keywordString, ' ,');

        } else {

        return $keywordString;

        }

    }


    /**
    * Slugifies a string.
    *
    * @param  string $text
    * @return string
    */
    private function slugify($text)
    {
    // replace non letter or digits by -
    $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
    // trim
    $text = trim($text, '-');
    // transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // lowercase
    $text = strtolower($text);
    // remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

        if (empty($text)){
            return false;
        }
      return $text;
    }


    /**
     * Create an excerpt.
     *
     *
     * @param string $text
     * @param int $max_length
     * @return string
     */
    private function makeExcerpt($text, $max_length)
    {
        return substr(strip_tags((string)$text), 0, $max_length).'...';
    }


    /**
     * Send a notification to a group of a users.
     *
     * @param Notification $notification
     * @param User $users
     * @return null
     */
    private function notifier(Notification $notification, Study $study, $users)
    {
        $notification->study()->associate($study);
        $notification->save();

        foreach($users as $user) {
            // dont send a notification to the author
            if($user->id !== Auth::user()->id) {
                $notification->users()->attach($user->id);
            }
        }
    }


    /**
     * Check if a user has access to a route.
     *
     * @return bool
     */
    private function checkAccess()
    {
        $user = Sentinel::findById(Auth::user()->id);

        if($user->hasAccess([Request::route()->getName()])) {
            return true;
        } else {
            return false;
        }

    }
}


