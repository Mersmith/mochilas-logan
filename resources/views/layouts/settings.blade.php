@if(auth()->user()->hasRole('cliente'))
    @component('layouts.publico')
        {{ $slot }}
    @endcomponent
@else
    @component('layouts.app')
        {{ $slot }}
    @endcomponent
@endif
