<x-layouts.app :title="__('العملاء')">
    <x-page-header :title="__('العملاء')" :subtitle="__('ابحث بالاسم أو رقم الجوال (3 أحرف على الأقل). فتح أي ملف سريري يُسجَّل في سجل التدقيق.')" />

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3" role="search">
        <div class="min-w-64 flex-1">
            <label for="q" class="label">{{ __('بحث') }}</label>
            <input id="q" name="q" value="{{ $term }}" class="input" minlength="3" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-primary">{{ __('بحث') }}</button>
    </form>

    @if ($term === '')
        <x-empty :title="__('ابدأ البحث')" :text="__('لا نعرض قائمة كاملة بالعملاء حمايةً لخصوصيتهم.')" />
    @elseif (mb_strlen($term) < 3)
        <p class="alert alert-warning">{{ __('اكتب 3 أحرف على الأقل.') }}</p>
    @elseif ($clients->isEmpty())
        <x-empty :title="__('لا توجد نتائج')" />
    @else
        <ul class="grid gap-3">
            @foreach ($clients as $client)
                <li><a href="{{ route('practitioner.clients.show', $client) }}" class="card block no-underline hover:border-petrol">
                    <span class="font-semibold">{{ $client->name }}</span>
                    <span class="text-ink-soft"> · <bdi>{{ \App\Support\Phone::mask($client->phone) }}</bdi>@if ($client->city) · {{ $client->city }}@endif</span>
                </a></li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
