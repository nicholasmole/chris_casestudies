@extends('admin-base')

@section('content')

<section id="heading">
    @include('layouts.admin.partials._heading', ['heading' => 'Add New User'])
    {!! Breadcrumbs::render('create-user') !!}
</section>

@include('layouts.admin.partials._success')
@include('layouts.admin.partials._errors')


{!! Form::open(['route' => 'admin.users.store']) !!}

@include('layouts.admin.partials._users-form', ['edit' => false])

{!! Form::submit('Add User', ['class' => 'btn btn-primary form-control']) !!}

{!! Form::close() !!}

@stop