<nav aria-label="{{ $courseNavigationLabel ?? 'Learning course' }}" class="mb-6 flex flex-wrap gap-3">
    @foreach(['cpp' => 'C++ Programming', 'arduino-uno' => 'Arduino Uno Robotics'] as $navigationCourse => $navigationLabel)
        <a href="{{ route($courseNavigationRoute, $navigationCourse === 'arduino-uno' ? ['course' => 'arduino-uno'] : []) }}"
           @if($selectedCourse === $navigationCourse) aria-current="page" @endif
           class="inline-flex items-center rounded-xl border px-4 py-3 text-sm font-bold text-black {{ $selectedCourse === $navigationCourse ? 'border-primary bg-secondary-soft shadow-[inset_3px_0_0_#0b4e00]' : 'border-green-200 bg-white hover:bg-paper' }}">
            {{ $navigationLabel }}
        </a>
    @endforeach
</nav>
