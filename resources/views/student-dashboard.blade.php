@extends('layouts.student',['pageTitle'=>'Dashboard','active'=>'dashboard'])
@section('content')
<div data-dashboard-live="{{ route('student.dashboard.status') }}">@include('dashboard.student-overview')</div>
@include('dashboard-achievements')
@include('student-alerts-card')
<p class="mt-4 text-xs text-secondary">Dashboard records refresh every 30 seconds while the page is visible.</p>
<script src="{{ asset('js/dashboard-live.js') }}" defer></script>
@endsection