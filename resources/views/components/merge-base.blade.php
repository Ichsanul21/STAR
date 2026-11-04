@props(['model', 'fields' => []])

@php
    $snapshot = [];
    foreach ($fields as $field) {
        $value = $model->getAttribute($field);
        $snapshot[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
    }
@endphp

<input type="hidden" name="base_version" value="{{ $model->lock_version }}">
<input type="hidden" name="base_snapshot" value="{{ json_encode($snapshot) }}">
