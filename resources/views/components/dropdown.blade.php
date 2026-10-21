@props(['align' => 'right', 'width' => '48', 'contentClasses' => null])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};

$esAdmin = auth()->user()?->hasRole('supervisor') ?? false;
$contentClasses ??= $esAdmin ? 'py-1 bg-white dark:bg-stone-800' : 'py-1 bg-white dark:bg-azul-superficie-oscura';
$anillo = $esAdmin ? 'ring-arena-medio dark:ring-stone-700' : 'ring-celeste dark:ring-azul-borde-oscuro';
@endphp

<div class="relative" x-data="{ open: false }" @click.outside="open = false" @close.stop="open = false">
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-md ring-1 {{ $anillo }} {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
