@if(!empty($teacherProfile))
<section class="mb-6 flex items-start gap-4 rounded-2xl border border-secondary-soft bg-white p-5 shadow-card" aria-label="Your classroom teacher">
@include('admin-avatar',['adminAvatarSize'=>'medium'])
<div><p class="font-mono text-[10px] font-bold uppercase tracking-widest text-secondary">Your teacher</p><h2 class="mt-1 text-lg font-bold text-primary">{{ $teacherProfile['name'] }}</h2>@if($teacherProfile['bio'])<p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted">{{ $teacherProfile['bio'] }}</p>@endif @if($teacherProfile['avatar'])<img src="{{ $teacherProfile['avatar'] }}" alt="Classroom teacher photo" class="mt-3 h-14 w-14 rounded-xl object-cover">@endif</div>
</section>
@endif
