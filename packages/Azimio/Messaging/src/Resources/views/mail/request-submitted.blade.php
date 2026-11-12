<p>@lang('messaging::app.requests.mail.submitted-intro', ['tenant' => $tenant->name])</p>

<ul>
    <li>@lang('messaging::app.super.tenant'): <strong>{{ $tenant->name }}</strong></li>
    <li>@lang('messaging::app.requests.requested'): <strong>{{ number_format($creditRequest->credits_requested) }}</strong></li>
    <li>@lang('messaging::app.sms.settings.credit-balance'): <strong>{{ number_format($tenant->sms_credits) }}</strong></li>
</ul>

@if ($creditRequest->reason)
    <p>@lang('messaging::app.requests.reason'):<br>{{ $creditRequest->reason }}</p>
@endif

<p>
    <a href="{{ route('super.sms.requests.index') }}">
        @lang('messaging::app.requests.mail.review-link')
    </a>
</p>
