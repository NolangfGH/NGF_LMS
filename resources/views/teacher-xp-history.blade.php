@extends('layouts.teacher-learning',['pageTitle'=>'Student XP History'])
@section('content')
<a href="{{ route('teacher.achievements') }}" class="text-sm font-bold text-primary underline">← Student achievements</a>
<h1 class="mt-5 text-3xl font-extrabold text-primary">{{ $learner->nickname }} · XP record</h1><p class="mt-3 text-sm">{{ $learner->classroom->grade }} · {{ $learner->classroom->section ?: $learner->classroom->name }} · {{ $learner->student_id }}</p><p class="mt-2 text-sm leading-6">Use saved activity receipts to explain earnings and deductions. This page is read-only; it does not change the learner’s balance.</p>
@include('xp-history')
@endsection
