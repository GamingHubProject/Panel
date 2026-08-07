@extends('admin.layouts.admin')

@section('title', 'Add Panel Connection')

@section('content')
<form class="card" method="POST" action="{{ route('gaming-hub-panel.admin.connections.store') }}">
    <div class="card-header">Connection details</div>
    <div class="card-body">@include('gaming-hub-panel::admin.connections.partials.form')</div>
</form>
@endsection
