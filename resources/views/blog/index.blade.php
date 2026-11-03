@extends($isTeacher ? 'layouts.teacher-learning' : 'layouts.student')
@section('content')
@include('blog.notice')
@if($isTeacher)@include('teaching-library-link')@endif
<section class="mb-7 flex flex-wrap items-end justify-between gap-5">
    <div class="max-w-2xl"><span class="rounded-full bg-secondary-soft px-3 py-1 font-mono text-[10px] font-bold uppercase tracking-wider text-primary">Grade 8 · C++ learning hub</span><h1 class="mt-4 text-3xl font-extrabold tracking-tight text-primary sm:text-4xl">NGF CodeQuest Blog</h1><p class="mt-3 text-sm leading-7 text-muted">{{ $isTeacher ? 'Share a programming idea, a worked example or a visual coding task. Guide each learner through private feedback.' : 'Explore C++ tips and ideas from your teacher. Share your thinking in a private conversation.' }}</p></div>
    @if($isTeacher)<a href="{{ route('teacher.blog.create') }}" class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-tactile">＋ Write an article</a>@endif
</section>
<div class="mb-7 grid gap-3 sm:grid-cols-3">
    <div class="rounded-2xl border border-black/5 bg-white p-4"><b class="text-2xl text-primary">{{ $publishedCount }}</b><p class="mt-1 text-xs text-secondary">Published {{ Str::plural('article', $publishedCount) }}</p></div>
    @if($isTeacher)
    <div class="rounded-2xl border border-black/5 bg-white p-4"><b class="text-2xl text-primary">{{ $draftCount }}</b><p class="mt-1 text-xs text-secondary">Private {{ Str::plural('draft', $draftCount) }}</p></div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4"><b class="text-2xl text-amber-900">{{ $waitingCount }}</b><p class="mt-1 text-xs text-amber-900">Student {{ Str::plural('comment', $waitingCount) }} awaiting your response</p></div>
    @else
    <div class="rounded-2xl border border-green-200 bg-green-50 p-4 sm:col-span-2"><span class="material-symbols-outlined align-middle text-primary" aria-hidden="true">lock</span><b class="ml-1 text-sm text-primary">Your insights stay private</b><p class="mt-2 text-xs leading-5 text-secondary">Only you and the teacher who posted the article can see your comments, uploads and replies. Classmates see the article only.</p></div>
    @endif
</div>
<form method="GET" action="{{ route($blogRoute) }}" class="mb-5 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-card" role="search">
    <label class="min-w-0 flex-1 text-xs font-bold text-primary">Search articles<input name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="Search C++ topics, loops, variables…" class="mt-2 w-full rounded-xl border border-green-200 bg-paper p-3 text-sm font-normal"></label>
    <label class="text-xs font-bold text-primary">Topic<select name="category" class="mt-2 block w-full rounded-xl border border-green-200 bg-white p-3 text-sm font-normal"><option value="">All topics</option>@foreach($categories as $category)<option @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>@endforeach</select></label>
    @if($isTeacher)<label class="text-xs font-bold text-primary">Visibility<select name="status" class="mt-2 block rounded-xl border border-green-200 bg-white p-3 text-sm font-normal"><option value="">All articles</option><option value="published" @selected(($filters['status'] ?? '') === 'published')>Published</option><option value="draft" @selected(($filters['status'] ?? '') === 'draft')>Drafts</option></select></label>@endif
    <button class="rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white">Find articles</button><a href="{{ route($blogRoute) }}" class="px-2 py-3 text-sm font-bold text-secondary">Reset</a>
</form>
@if($posts->isNotEmpty())
@php($featured = $posts->first())
<section class="mb-7 grid overflow-hidden rounded-3xl border border-green-200 bg-gradient-to-br from-green-100 via-paper to-white shadow-card md:grid-cols-[1.3fr_1fr]">
    <div class="p-6 sm:p-8"><div class="flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full bg-white px-3 py-1 text-primary">{{ $featured->category }}</span><span class="rounded-full bg-secondary-soft px-3 py-1 text-primary">{{ $featured->readingMinutes() }} min read</span><span class="rounded-full bg-white px-3 py-1 text-secondary">{{ $featured->status === 'draft' ? 'Draft · teacher only' : 'Latest article' }}</span></div><h2 class="mt-5 break-words text-2xl font-extrabold leading-tight text-primary sm:text-3xl">{{ $featured->title }}</h2><p class="mt-4 whitespace-pre-wrap break-words text-sm leading-7">{{ $featured->excerpt() }}</p><div class="mt-5 flex items-center gap-3">@include('admin-avatar')<div><p class="text-xs font-bold text-primary">{{ $featured->teacher->fullName() }}</p><p class="mt-1 text-xs text-secondary">{{ ($featured->published_at ?? $featured->created_at)->timezone(config('learning.timezone'))->format('M j, Y') }}</p></div></div><a href="{{ route($blogRoute.'.show', $featured) }}" class="mt-6 inline-block rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-tactile">Read & reflect →</a></div>
    <div class="grid min-h-56 place-items-center bg-white/70 p-6">@if($featured->image_path)<img src="{{ route($blogRoute.'.image', $featured) }}" alt="Article illustration: {{ $featured->title }}" class="max-h-80 w-full rounded-2xl object-contain" loading="lazy">@else<div class="w-full rounded-2xl bg-[#102719] p-6 font-mono text-sm text-green-100"><p class="text-lime-300">// Read. Think. Share.</p><pre class="mt-4 overflow-x-auto leading-7">#include &lt;iostream&gt;
int main() {
  std::cout &lt;&lt; "Keep exploring!";
  return 0;
}</pre><p class="mt-5 border-t border-white/15 pt-4 font-sans text-xs leading-6">Explain one idea you learned, ask a question, or upload a code screenshot for your teacher.</p></div>@endif</div>
</section>
<div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-lg font-bold text-primary">Article library</h2><p class="text-xs text-secondary">{{ $posts->total() }} {{ Str::plural('result', $posts->total()) }}</p></div>
<section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3" aria-label="Articles">
@foreach($posts as $article)
<article class="flex flex-col overflow-hidden rounded-2xl border border-black/5 bg-white shadow-card">@if($article->image_path)<img src="{{ route($blogRoute.'.image', $article) }}" alt="Illustration for {{ $article->title }}" loading="lazy" class="h-44 w-full bg-paper object-contain">@endif<div class="flex flex-1 flex-col p-5"><div class="flex flex-wrap justify-between gap-2 text-[11px] font-bold text-secondary"><span class="rounded-lg bg-paper px-2 py-1">{{ $article->category }}</span><span>{{ $article->readingMinutes() }} min read</span></div><h3 class="mt-4 break-words text-lg font-bold text-primary"><a href="{{ route($blogRoute.'.show', $article) }}" class="hover:underline">{{ $article->title }}</a></h3><p class="mt-3 flex-1 break-words text-sm leading-6">{{ $article->excerpt() }}</p><div class="mt-5 flex flex-wrap items-center justify-between gap-2 border-t border-green-100 pt-4 text-xs"><span>{{ $article->status === 'draft' ? 'Draft · teacher only' : $article->published_at?->timezone(config('learning.timezone'))->format('M j, Y') }}</span><a href="{{ route($blogRoute.'.show', $article) }}" class="font-bold text-primary">{{ $isTeacher ? $article->comments_count.' private comments' : 'Read article' }} →</a></div>@if($isTeacher)<a href="{{ route('teacher.blog.edit', $article) }}" class="mt-4 text-xs font-bold text-secondary underline">Edit article</a>@endif</div></article>
@endforeach
</section>
<div class="mt-7">{{ $posts->links() }}</div>
@else
<section class="rounded-3xl border-2 border-dashed border-secondary-soft bg-white px-6 py-14 text-center"><span class="material-symbols-outlined text-5xl text-secondary" aria-hidden="true">auto_stories</span><h2 class="mt-4 text-xl font-bold text-primary">{{ filled($filters['search'] ?? null) || filled($filters['category'] ?? null) || filled($filters['status'] ?? null) ? 'No matching articles' : 'Your C++ reading journey starts here' }}</h2><p class="mx-auto mt-3 max-w-lg text-sm leading-7">{{ $isTeacher ? 'Create your first article with text, a topic image, or both. Published articles reach students across your sections.' : 'Your teacher’s published articles will appear here. Try another filter or return after a topic is posted.' }}</p></section>
@endif
@endsection
