@extends('admin-base')

@section('bodyclass', 'manage_case_studies')

@section('content')

@include('layouts.admin.partials._heading', ['heading' => 'Trashed Studies'])

{!! Breadcrumbs::render('trash') !!}

@include('layouts.admin.partials._success')
@include('layouts.admin.partials._errors')

@if($studies->isEmpty())
    <h3>There are no studies in the trash.</h3>
@else

<table class="table table-hover" data-resource="draft">
    <thead>
        <tr>
            <th>Title</th>
            <th>Options</th>
        </tr>
    </thead>
    <tbody>

        @foreach($studies as $study)
            <tr>
                <td><a href="{{ route('admin.cases.show', ['slug' => $study->slug]) }}" data-toggle="modal" data-target="#study" class="case-study">{{ $study->title }}</a></td>
                <td><a href="{{ route('admin.cases.restore', ['slug' => $study->slug]) }}">Move to Drafts</a></td>
            </tr>
        @endforeach

    </tbody>
</table>
@endif

@include('layouts.admin.partials._study-modal')

@stop