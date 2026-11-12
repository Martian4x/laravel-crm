<p>@lang('messaging::app.requests.mail.reviewed-intro')</p>

<ul>
    <li>@lang('messaging::app.requests.requested'): <strong>{{ number_format($creditRequest->credits_requested) }}</strong></li>
    <li>@lang('messaging::app.requests.granted'): <strong>{{ number_format((int) $creditRequest->credits_granted) }}</strong></li>
    <li>@lang('messaging::app.sms.log.status'): <strong>@lang('messaging::app.requests.status.' . str_replace('_', '-', $creditRequest->status->value))</strong></li>
</ul>

@if ($creditRequest->admin_note)
    <p>{{ $creditRequest->admin_note }}</p>
@endif
