<p>@lang('messaging::app.requests.mail.low-balance-intro', [
    'balance'   => number_format((int) $tenant->sms_credits),
    'threshold' => number_format($threshold),
])</p>

<p>
    <a href="{{ route('admin.sms.credits.index') }}">
        @lang('messaging::app.requests.new-title')
    </a>
</p>
