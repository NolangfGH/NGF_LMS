@foreach(\App\Services\IlawPlanTemplate::GROUPS as $heading => $fields)
    <section>
        <h2 class="mb-3 text-lg font-extrabold text-primary">{{ $heading }}</h2>
        <dl class="space-y-3">
            @foreach($fields as $field => [$label, $hint])
                <div>
                    <dt class="font-bold">{{ $label }}</dt>
                    <dd class="whitespace-pre-wrap break-words">{{ $content[$field] ?? '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endforeach
