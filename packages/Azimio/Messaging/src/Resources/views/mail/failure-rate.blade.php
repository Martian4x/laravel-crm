<p>@lang('messaging::app.super.alert.intro', [
    'tenant' => $tenant->name,
    'rate'   => $rate,
    'failed' => number_format($failed),
    'total'  => number_format($total),
])</p>

<p>
    <a href="{{ route('super.sms.messages', ['tenant_id' => $tenant->id]) }}">
        @lang('messaging::app.super.all-messages')
    </a>
</p>
