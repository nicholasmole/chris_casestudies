@extends('admin-base')
@section('bodyclass', 'manage_case_studies trashed_studies')
@section('sectionid', 'manage-trashed')

@section('content')
<section id="heading">
    <h3 class="page-title">Trashed Studies</h3>
    {!! Breadcrumbs::render('trash') !!}
</section>

@include('layouts.admin.partials._success')
@include('layouts.admin.partials._errors')

<section id="trashed-cases" class="card">
    @if($studies->isEmpty())
        <h3>There are no studies in the trash.</h3>
    @else
        <div class="card-header">
            <a href="{{ route('admin.cases.drafts') }}"><button type="button" class="btn btn-primary">View Drafts</button></a>
            <div class="right">
                <span class="checked-count"></span>

                @include('layouts.admin.partials._card-header-menu', ['menu' => 'trash'])

            </div>
        </div>

        <table id="trashed-studies-table" class="table table-hover table-responsive" data-resource="draft">
            <thead>
                <tr>
                    <th><input name="master-check" type="checkbox" value="" class="checkbox-custom master-check" id="master-check" data-name="studies[]"><label class="checkbox-custom-label" for="master-check"></label></th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>

                @foreach($studies as $study)
                    <tr>
                        <td><input name="studies[]" type="checkbox" value="{{ $study->id }}" class="checkbox-custom" id="c{{ $study->id }}"><label class="checkbox-custom-label" for="c{{ $study->id }}"></label></td>
                        <td><a href="{{ route('admin.cases.show', ['slug' => $study->slug]) }}" data-toggle="modal" data-target="#study" class="case-study">{{ $study->title }}</a></td>
                        <td>{{ $study->user->first_name.' '.$study->user->last_name }}</td>
                        <td>{{ date('F d, Y', strtotime($study->created_at)) }}</td>
                    </tr>
                @endforeach

            </tbody>
        </table>

        {!! Form::open(['method' => 'DELETE', 'id' => 'form-delete']) !!}
        {!! Form::close() !!}

        {!! Form::open(['method' => 'PUT', 'id' => 'form-restore']) !!}
        {!! Form::close() !!}

        @include('layouts.admin.partials._study-modal')

    @endif
</section>


@stop