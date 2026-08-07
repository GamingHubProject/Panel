@extends('admin.layouts.admin')
@section('title', trans('gaming-hub-core::admin.providers.edit'))
@section('content')
<div class="card shadow"><div class="card-body">
    <form method="POST" action="{{ route('gaming-hub-panel.admin.providers.update',[$game,$server,$provider]) }}">
        @include('gaming-hub-core::admin.providers._form')
    </form>
</div></div>
@endsection
