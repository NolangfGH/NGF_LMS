@foreach(\App\Services\SchoolOverviewService::TABS as $principalTab=>$principalLabel)
<a href="{{ route('school.overview',['tab'=>$principalTab]) }}" @if(request()->routeIs('school.overview') && request('tab','overview') === $principalTab) aria-current="page" @endif class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-bold {{ request()->routeIs('school.overview') && request('tab','overview') === $principalTab ? 'bg-primary text-white' : 'text-primary hover:bg-green-50' }}">{{ $principalLabel }}</a>
@endforeach
<a href="{{ route('teacher.settings') }}" @if(request()->routeIs('teacher.settings')) aria-current="page" @endif class="mt-3 block rounded-xl border-t border-green-100 px-4 py-3 text-sm font-bold text-primary">Account Settings</a>
