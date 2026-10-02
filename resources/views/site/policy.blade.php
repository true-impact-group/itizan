<x-layouts.site :title="$page->title()">
    <article class="section max-w-4xl">
        <p class="mb-2"><a href="{{ route('policies.index') }}">← {{ __('كل السياسات') }}</a></p>
        <h1 class="text-4xl">{{ $page->title() }}</h1>
        <p class="mt-1 text-sm text-ink-soft">{{ __('آخر تحديث: :date', ['date' => $page->updated_at->translatedFormat('j F Y')]) }}</p>
        <div class="prose-itizan mt-6">{{ $page->renderedBody() }}</div>
    </article>
</x-layouts.site>
