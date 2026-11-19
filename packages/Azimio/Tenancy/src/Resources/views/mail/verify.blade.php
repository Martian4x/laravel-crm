<p>@lang('azimio::app.verify.mail-intro', ['name' => $user->name])</p>

<p>
    <a href="{{ $url }}">@lang('azimio::app.verify.mail-button')</a>
</p>

<p>@lang('azimio::app.verify.mail-expiry')</p>

<p style="font-size:12px;color:#666">
    @lang('azimio::app.verify.mail-ignore')
</p>
