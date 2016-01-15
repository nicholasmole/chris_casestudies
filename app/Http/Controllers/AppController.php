<?php

namespace App\Http\Controllers;

use Request;
use Session;
use Input;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Pagination\LengthAwarePaginator;


use App\Http\Requests\SearchRequest;

use App\Study;
use App\Keyword;
use App\Outcome;
use App\Course;

class AppController extends Controller
{

    /**
     * The landing page.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        Session::forget(['search','results','studies_by_outcome','studies_by_course','outcomes_checked','courses_checked']);

        $outcomes = Outcome::latest()->get()->all();
        $courses = Course::latest()->get()->all();

        return view('layouts.app.landing')->with([
            'outcomes' => $outcomes,
            'courses'  =>$courses
        ]);

    }


    /**
     * Display results of a search.
     *
     * @return \Illuminate\Http\Response
     */
    public function results()
    {
        // @TODO: changing page in results breaks filters
        // because it just returns the given page
        // of the original result set.
        //
        // this method must somehow account for there being a
        // filter present.

        $studies = Session::get('results');
        $search = Session::get('search');

        if(!$studies) {
            return redirect(route('app.landing'));
        }

        $studies = $this->paginate($studies);

        return view('layouts.app.results')->with([
            'studies' => $studies,
            'search'  => $search
        ]);
    }


    /**
     * Show a single case study.
     *
     * @return \Illuminate\Http\Response
     */
    public function single($slug)
    {
        $study = Study::where('slug', $slug)->firstOrFail();

        return view('layouts.app.single')->with('study', $study);
    }


    /**
     * Search for case studies by keyword, outcome or course.
     *
     * @param  Request $SearchRequest
     * @return \Illuminate\Http\Response
     */
    public function search(SearchRequest $SearchRequest)
    {

        // @TODO: if request is tampered with and has 2 inputs
        switch($SearchRequest->input()):

            case $SearchRequest->has('keywords'):

                $search_terms = $SearchRequest->keywords;
                $studies = $this->keywordsSearch($SearchRequest);

                if($studies && !$studies->isEmpty()) {

                    $search = [
                        'terms' => $search_terms,
                        'type'  => 'keywords'
                    ];

                    Session::put([
                        'results' => $studies,
                        'search' => $search
                    ]);

                    return redirect(route('app.results'));

                } else {
                    return redirect(route('app.landing'))->withErrors('No results could be found for the entered keyword(s).');
                }

            break;

            case $SearchRequest->has('outcomes'):

                $search_terms = "outcomes go here";
                $studies = $this->outcomesSearch($SearchRequest);

                if($studies && !$studies->isEmpty()) {

                    $search = [
                        'terms' => $search_terms,
                        'type'  => 'outcomes'
                    ];

                    Session::put([
                        'results' => $studies,
                        'search' => $search
                    ]);

                    return redirect(route('app.results'));
                } else {
                    return redirect(route('app.landing'))->withErrors('No results could be found for the entered learning outcome(s).');
                }

            break;

            case $SearchRequest->has('courses'):

                $search_terms = "courses go here";
                $studies = $this->coursesSearch($SearchRequest);

                if($studies && !$studies->isEmpty()) {

                    $search = [
                        'terms' => $search_terms,
                        'type'  => 'courses'
                    ];

                    Session::put([
                        'results' => $studies,
                        'search' => $search
                    ]);

                    return redirect(route('app.results'));

                } else {

                    return redirect(route('app.landing'))->withErrors('No results could be found for the entered course(s).');
                }

            break;

            default:

                return redirect(route('app.landing'));

        endswitch;

    }

    /**
     * Filter Studies.
     *
     * @return  Response
     */
    public function filter()
    {
        $filter = Request::all();
        $studies_unfiltered = Session::get('results');
        $search = Session::get('search');

        switch(Request::input()):

            case Request::has('outcomes'):
                //filter studies by learning outcomes.
                if(Session::has('studies_by_course') && $search['type'] == 'keywords') {
                    // the results have been filtered by course
                    // filter studies_by_course by learning outcome
                    $studies_by_course = Session::get('studies_by_course');
                    $study_ids = $studies_by_course->lists('id');

                    $studies = $studies_by_course->filter(function($study){

                        $outcomes = Request::input('outcomes');

                        foreach($outcomes as $outcome){
                            foreach($study->outcomes as $study_outcome) {
                                // check all of a study's outcomes against each of the outcomes given.
                                if($study_outcome->id == $outcome){
                                    return $study;
                                }
                            }
                        }
                    });

                    $courses_checked = Session::get('courses_checked');
                    $outcomes_checked = Request::input('outcomes');

                    $studies->unique();

                    Session::put([
                        'search'             => $search,
                        'studies_by_outcome' => $studies,
                        'outcomes_checked'   => $outcomes_checked,
                        'courses_checked'    => $courses_checked
                    ]);

                    $studies = $this->paginate($studies);

                    return view('layouts.app.results')->with([
                        'studies'          => $studies,
                        'search'           => $search,
                        'outcomes_checked' => $outcomes_checked,
                        'courses_checked'  => $courses_checked
                    ]);

                } else {
                    // filter by outcome
                    $studies = $studies_unfiltered->filter(function ($study) {

                        $outcomes = Request::input('outcomes');
                        foreach($outcomes as $outcome) {
                            foreach($study->outcomes as $study_outcome) {
                                if($study_outcome->id == $outcome) {
                                    return $study;
                                }
                            }
                        }
                    });

                    $outcomes_checked = Request::input('outcomes');
                    $studies->unique();

                    Session::put([
                        'studies_by_outcome' => $studies,
                        'outcomes_checked'   => $outcomes_checked
                    ]);

                    $studies = $this->paginate($studies);

                    return view('layouts.app.results')->with([
                        'studies'          => $studies,
                        'search'           => $search,
                        'outcomes_checked' => $outcomes_checked
                    ]);
                }

            break;

            case Request::has('courses'):
                //filter studies by course
                if(Session::has('studies_by_outcome') && $search['type'] == 'keywords'){
                    // the results have been filtered by outcome
                    // filter studies_by_outcome by course
                    $studies_by_outcome = Session::get('studies_by_outcome');
                    $course_outcomes = $this->processCollection($studies_by_outcome->pluck('outcomes'));

                    $studies = [];
                    foreach($studies_by_outcome as $study) {
                        foreach($study->outcomes as $study_outcome) {
                            foreach($course_outcomes as $outcome) {
                                if($outcome->id == $study_outcome->id) {
                                    array_push($studies, $study);
                                }
                            }
                        }
                    }

                    $studies = collect($studies)->unique();

                    $courses_checked = Request::input('courses');
                    $outcomes_checked = Session::get('outcomes_checked');

                    Session::put([
                        'search'             => $search,
                        'studies_by_course'  => $studies,
                        'outcomes_checked'   => $outcomes_checked,
                        'courses_checked'    => $courses_checked
                    ]);

                    $studies = $this->paginate($studies);

                    return view('layouts.app.results')->with([
                        'studies'          => $studies,
                        'search'           => $search,
                        'courses_checked'  => $courses_checked,
                        'outcomes_checked' => $outcomes_checked
                    ]);

                } else {
                    // filter by course

                    // submitted filter
                    $courses = Course::whereIn('id', Request::input('courses'))->with('outcomes')->get();
                    $course_outcomes = $this->processCollection($courses->pluck('outcomes'));

                    // looks at each study
                    // looks at each outcome the study has
                    // for each outcome a study has, loop through
                    // the outcomes given by the filter that and try to
                    // find a match. If a match is found, add
                    // it to the array of results ($studies).
                    $studies = [];
                    foreach($studies_unfiltered as $study) {
                        foreach($study->outcomes as $study_outcome) {
                            foreach($course_outcomes as $outcome) {
                                if($outcome->id == $study_outcome->id) {
                                    array_push($studies, $study);
                                }
                            }
                        }
                    }

                    $studies = collect($studies)->unique();
                    $courses_checked = Request::input('courses');

                    Session::put([
                        'studies_by_course' => $studies,
                        'courses_checked'   => $courses_checked
                    ]);

                    $studies = $this->paginate($studies);

                    return view('layouts.app.results')->with([
                        'studies'         => $studies,
                        'search'          => $search,
                        'courses_checked' => $courses_checked
                    ]);
                }

            break;

            default:
            // reset filters
            // @TODO: filter resets are fucked
                $courses_checked = [];
                $outcomes_checked =[];

                if(Request::has('outcomes_reset')) {

                    Session::forget('studies_by_outcome');

                    if(Session::has('studies_by_course') && $search['type'] == 'keywords') {
                        // reset the outcomes filter but leave courses filter intact
                        $studies = Session::get('studies_by_course');

                        $courses_checked = Session::get('courses_checked');

                    } else {
                        // there was no courses filter present in the session
                        $studies = Session::get('results');
                    }

                } elseif(Request::has('courses_reset')) {

                    Session::forget('studies_by_course');

                    if(Session::has('studies_by_outcome') && $search['type'] == 'keyword') {
                        // reset the courses filter but leave outcomes filter intact
                        $studies = Session::get('studies_by_outcome');
                        $outcomes_checked = Session::get('outcomes_checked');
                    } else {
                        // there was no outcomes filter present in the sesssion
                        $studies = Session::get('results');
                    }

                } else {
                    // request doesn't have a reset, courses, or outcomes.
                }

                $studies = $this->paginate($studies);

                return view('layouts.app.results')->with([
                    'studies'          => $studies,
                    'search'           => $search,
                    'courses_checked'  => $courses_checked,
                    'outcomes_checked' => $outcomes_checked
                ]);

            break;


        endswitch;

    }


    /**
     * Search case studies by keywords.
     *
     * @param  SearchRequest $SearchRequest
     * @return Collection
     */
    private function keywordsSearch(SearchRequest $SearchRequest)
    {
        $keywordIds = $this->keywordsLookup($SearchRequest->input('keywords'));

        if(empty($keywordIds)) {
            return false;
        } else {

            $keywords = Keyword::whereIn('id', $keywordIds)->with('studies')->get();
            $studies = $keywords->pluck('studies');

            $studies_processed = $this->processCollection($studies);

            // eager load outcomes
            $studies = Study::with('outcomes')->whereIn('id',$studies_processed->lists('id'))->get();

            return $studies;
        }
    }


    /**
     * Search case studies by learning outcomes.
     *
     * @param  SearchRequest $SearchRequest
     * @return Collection
     */
    private function outcomesSearch(SearchRequest $SearchRequest, $outcomes = null)
    {

        if(!$outcomes && $SearchRequest->has('outcomes')) {

            $outcomeIds = $SearchRequest->outcomes;

        } elseif($outcomes && $SearchRequest->has('courses')) {

            $outcomeIds = $outcomes;

        } else {
            return false;
        }

        if(empty($outcomeIds)) {
            return false;
        } else {

            // get studies from outcomes
            $outcomes = Outcome::whereIn('id', $outcomeIds)->with('studies')->get();
            $studies = $outcomes->pluck('studies');

            $studies_processed = $this->processCollection($studies);

            // eager load outcomes
            $studies = Study::with('outcomes')->whereIn('id', $studies_processed->lists('id'))->get();

            return $studies;
        }

    }


    /**
     * Search case studies by courses.
     *
     * @param  SearchRequest $SearchRequest
     * @return Collection
     */
    private function coursesSearch(SearchRequest $SearchRequest)
    {
        $courseIds = $SearchRequest->courses;
        $courses = Course::whereIn('id', $courseIds)->with('outcomes')->get();

        $outcomes = $this->processCollection($courses->pluck('outcomes'));
        $outcomeIds = $outcomes->lists('id')->toArray();

        return $this->outcomesSearch($SearchRequest, $outcomeIds);
    }


    /**
     * Lookup given keywords and get IDs if present.
     *
     * @param  string $keywords
     * @return Array $keywordIds
     */
    private function keywordsLookup($keywords)
    {
        // explode string at commas and spaces and build array of keywords
        $keywords = array_map('trim', preg_split('~[\s,]+~', $keywords));

        $keywords = Keyword::whereIn('name', $keywords)->get();
        $keywordIds = $keywords->pluck('id');

        return $keywordIds->toArray();
    }


    /**
     * Paginate a collection of studies.
     *
     * @param Collection $studies
     * @param int $items
     * @return LengthAwarePaginator
     */
    private function paginate($items)
    {
        $page = Input::get('page', 1);
        $perPage = 5;

        $paginated = new LengthAwarePaginator($items->forPage($page,$perPage), $items->count(), $perPage, $page);
        $paginated->setPath('/results');

        return $paginated;
    }


    /**
     * Collect, collapse, and eliminate duplicate models.
     *
     * @param  array $collection
     * @return Collection
     */
    private function processCollection($array)
    {
        $array_collapsed = collect($array)->collapse();
        $collection = $array_collapsed->unique(function($item) {
            return $item['id'];
        });

        return $collection;
    }

}
