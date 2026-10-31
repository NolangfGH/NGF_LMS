<section class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="All-section roster summary">
@foreach([['Active students',$metrics['active'],'Across all your sections'],['Teaching sections',$metrics['sections'],'Your saved grade and section groups'],['Learners needing support',$metrics['support'],'Based on saved learning evidence'],['Deleted students',$metrics['deleted'],'Work retained; accounts can be restored']] as [$label,$value,$caption])
<article class="rounded-2xl bg-white p-5 shadow-card"><p class="text-xs font-bold text-secondary">{{ $label }}</p><strong class="mt-2 block text-3xl text-primary">{{ number_format($value) }}</strong><p class="mt-2 text-xs leading-5 text-muted">{{ $caption }}</p></article>
@endforeach
</section>
