{{--
    The access log of one link. Only time, outcome, action and a shortened user agent; the IP hash
    is pseudonymous personal data and stays out of the UI.
--}}
@php
    $t = fn (string $key): string => __('filament-public-links::filament-public-links.filament.'.$key);
@endphp
@if ($accesses->isEmpty())
    <p>{{ $t('actions.access_log.empty') }}</p>
@else
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
            <thead>
                <tr style="text-align: start;">
                    <th scope="col" style="padding: .5rem; text-align: start;">{{ $t('actions.access_log.time') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: start;">{{ $t('actions.access_log.outcome') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: start;">{{ $t('actions.access_log.action') }}</th>
                    <th scope="col" style="padding: .5rem; text-align: start;">{{ $t('actions.access_log.user_agent') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($accesses as $access)
                    <tr style="border-top: 1px solid rgba(127, 127, 127, .25);">
                        <td style="padding: .5rem; white-space: nowrap;">{{ $access->accessed_at->toDateTimeString() }}</td>
                        <td style="padding: .5rem;">{{ $t('outcomes.'.$access->outcome) }}</td>
                        <td style="padding: .5rem;">{{ $access->action ?? '—' }}</td>
                        <td style="padding: .5rem;" title="{{ $access->user_agent }}">{{ \Illuminate\Support\Str::limit((string) $access->user_agent, 80) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
