@props(['name', 'value' => '', 'label' => null])
@php
    $html = app(\App\Services\HtmlSanitizer::class)->clean(old($name, $value));
@endphp

<div data-rich-text class="rich-text">
    @if ($label)
        <label class="label">{{ $label }}</label>
    @endif
    <div class="editor-toolbar" data-toolbar>
        <button type="button" data-action="bold" title="Bold"><strong>B</strong></button>
        <button type="button" data-action="italic" title="Italic"><em>I</em></button>
        <button type="button" data-action="strike" title="Strike"><s>S</s></button>
        <span class="editor-sep"></span>
        <button type="button" data-action="h2" title="Heading 2">H2</button>
        <button type="button" data-action="h3" title="Heading 3">H3</button>
        <span class="editor-sep"></span>
        <button type="button" data-action="bullet" title="Bullet list">• List</button>
        <button type="button" data-action="ordered" title="Numbered list">1. List</button>
        <button type="button" data-action="quote" title="Quote">❝</button>
        <button type="button" data-action="link" title="Link">Link</button>
        <span class="editor-sep"></span>
        <button type="button" data-action="undo" title="Undo">↶</button>
        <button type="button" data-action="redo" title="Redo">↷</button>
    </div>
    <div data-editor class="editor-content">{!! $html !!}</div>
    <input type="hidden" name="{{ $name }}" value="{{ $html }}">
</div>
