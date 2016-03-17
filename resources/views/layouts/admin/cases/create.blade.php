@extends('admin-base')

@section('bodyclass', 'case_study_editor')


@section('content')

<main id="cases-form">

    <section id="heading">
        @include('layouts.admin.partials._heading', ['heading' => 'Add New Case Study'])
        {!! Breadcrumbs::render('create') !!}
    </section>

    @include('layouts.admin.partials._success')
    @include('layouts.admin.partials._errors')

    <section id="create-case">
    {!! Form::open(['route' => 'admin.cases.store']) !!}

        @include('layouts.admin.partials._cases-form', ['create' => true])

        <div class="row card">
            <div class="col-lg-12">
                @if(Sentinel::findById(Auth::user()->id)->hasAccess(['publish']))
                    {!! Form::submit('Publish Case Study', ['class' => 'btn btn-primary', 'name' => 'publish']) !!}
                @endif
                    {!! Form::submit('Save Draft', ['class' => 'btn btn-secondary', 'name' => 'draft']) !!}
            </div>
        </div>

    {!! Form::close() !!}
    </section>

</main>

@stop