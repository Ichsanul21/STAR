@props(['value' => 0, 'class' => ''])

<span {{ $attributes->merge(['class' => $class]) }}>Rp{{ number_format((float) $value, 0, ',', '.') }}</span>
