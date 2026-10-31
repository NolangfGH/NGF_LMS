@extends('layouts.teacher-learning',['pageTitle'=>'Teacher Dashboard'])
@section('content')
<div data-dashboard-live="{{ route('teacher.dashboard.status') }}">@include('dashboard.teacher-overview')</div>
<p class="mt-4 text-xs text-secondary">Dashboard records refresh every 30 seconds while the page is visible.</p>
<script src="{{ asset('js/dashboard-live.js') }}" defer></script>
@endsection