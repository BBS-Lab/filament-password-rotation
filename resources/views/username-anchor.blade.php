{{--
    Password-manager anchor: a hidden username lets Chrome recognise this as a
    change-password form for a known account, so saved-password autofill and the
    strong-password generator target the right fields and do not overwrite the
    current-password field. See the Chromium "Create Amazing Password Forms"
    guidance. It is readonly and never submitted (Filament state is Livewire-bound).
--}}
<input
    type="text"
    name="username"
    autocomplete="username"
    value="{{ filament()->auth()->user()?->getAttribute('email') ?? '' }}"
    tabindex="-1"
    aria-hidden="true"
    readonly
    style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;"
>
