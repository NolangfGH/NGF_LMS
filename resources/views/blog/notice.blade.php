@if(session('blog_notice'))
<p role="status" class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm font-bold text-primary">{{ session('blog_notice') }}</p>
@endif
@if(!$isTeacher && $errors->any())
<div role="alert" class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900"><b>Please check your comment.</b><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul><p class="mt-2">For a regular form, choose your image or voice file again before resending.</p></div>
@endif
