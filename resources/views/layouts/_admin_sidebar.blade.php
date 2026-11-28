@php $navigation = app(\App\Support\AdminNavigation::class)->for(auth()->user()); @endphp
<ul class="sidebar-nav">
    @foreach($navigation as $section)
        @if($section['header'])
            <li class="sidebar-header">{{ $section['header'] }}</li>
        @endif

        @foreach($section['items'] as $item)
            @if($item['children'])
                <li class="sidebar-item {{ $item['active'] ? 'active' : '' }}">
                    <a class="sidebar-link {{ $item['active'] ? '' : 'collapsed' }}" href="#"
                       data-bs-toggle="collapse" data-bs-target="#collapse-{{ $item['id'] }}"
                       aria-expanded="{{ $item['active'] ? 'true' : 'false' }}">
                        <i class="bi {{ $item['icon'] }}"></i> <span>{{ $item['label'] }}</span>
                    </a>
                    <ul id="collapse-{{ $item['id'] }}" class="sidebar-dropdown list-unstyled collapse {{ $item['active'] ? 'show' : '' }}">
                        @foreach($item['children'] as $child)
                            <li class="sidebar-item {{ $child['active'] ? 'active' : '' }}">
                                <a class="sidebar-link" href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @else
                <li class="sidebar-item {{ $item['active'] ? 'active' : '' }}">
                    <a class="sidebar-link" href="{{ $item['url'] }}">
                        <i class="bi {{ $item['icon'] }}"></i> <span>{{ $item['label'] }}</span>
                        @if($item['badge'])
                            <span class="badge bg-warning text-dark ms-1" title="{{ $item['badge'] }}">!</span>
                        @endif
                    </a>
                </li>
            @endif
        @endforeach
    @endforeach
</ul>
