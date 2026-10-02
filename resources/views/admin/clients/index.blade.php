@php use App\Support\Phone; @endphp
<x-layouts.app :title="__('العملاء')">
    <x-page-header :title="__('العملاء')" />

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3" role="search">
        <div class="min-w-64 flex-1"><label for="q" class="label">{{ __('بحث بالاسم أو الجوال أو البريد') }}</label><input id="q" name="q" value="{{ $term }}" class="input"></div>
        <button type="submit" class="btn btn-primary">{{ __('بحث') }}</button>
    </form>

    @if ($clients->isEmpty())
        <x-empty :title="__('لا توجد نتائج')" />
    @else
        <div class="table-wrap">
            <table class="table">
                <caption class="sr-only">{{ __('العملاء') }}</caption>
                <thead><tr><th scope="col">{{ __('الاسم') }}</th><th scope="col">{{ __('الجوال') }}</th><th scope="col">{{ __('المدينة') }}</th><th scope="col">{{ __('اشتراك فعّال') }}</th><th scope="col">{{ __('الحساب') }}</th><th scope="col">{{ __('انضم') }}</th></tr></thead>
                <tbody>
                @foreach ($clients as $client)
                    <tr>
                        <td><a href="{{ route('admin.clients.show', $client) }}" class="font-semibold">{{ $client->name }}</a></td>
                        <td><bdi>{{ Phone::mask($client->phone) }}</bdi></td>
                        <td>{{ $client->city ?? '—' }}</td>
                        <td>{{ $client->active_subscriptions_count ? __('نعم') : __('لا') }}</td>
                        <td>@if ($client->is_active)<x-badge tone="success" :label="__('مفعّل')" />@else<x-badge tone="danger" :label="__('موقوف')" />@endif</td>
                        <td>{{ $client->created_at->translatedFormat('j F Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $clients->links() }}
    @endif
</x-layouts.app>
