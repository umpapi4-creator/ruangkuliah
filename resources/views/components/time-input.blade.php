@php
    $timeName = $name ?? 'time';
    $timeId = $id ?? $timeName;
    $rawTime = (string) ($value ?? '');
    $normalizedTime = '';
    $displayTime = '';

    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)/', $rawTime, $timeParts)) {
        $normalizedTime = $timeParts[1].':'.$timeParts[2];
        $displayTime = $timeParts[1].'.'.$timeParts[2];
    }
@endphp

<div class="time-field">
    <input type="hidden" name="{{ $timeName }}" id="{{ $timeId }}" value="{{ $normalizedTime }}">
    <input
        class="form-control time-display"
        type="text"
        id="{{ $timeId }}_display"
        value="{{ $displayTime }}"
        placeholder="00.00"
        inputmode="numeric"
        maxlength="5"
        autocomplete="off"
        aria-label="{{ $label ?? 'Jam 24 jam' }}"
        pattern="(?:[01][0-9]|2[0-3])\.[0-5][0-9]"
        title="Masukkan jam 24 jam dengan format 00.00 sampai 23.59"
        @if($required ?? false) required @endif
    >
    <span class="time-format-badge">24H</span>
</div>

<script>
(() => {
    const hidden = document.getElementById(@json($timeId));
    const display = document.getElementById(@json($timeId.'_display'));

    const syncHidden = () => {
        const match = display.value.match(/^([01]\d|2[0-3])\.([0-5]\d)$/);
        hidden.value = match ? `${match[1]}:${match[2]}` : '';
        display.setCustomValidity(display.value && !match ? 'Gunakan format jam 24 jam, misalnya 08.30 atau 23.59.' : '');
    };

    display.addEventListener('input', () => {
        const digits = display.value.replace(/\D/g, '').slice(0, 4);
        display.value = digits.length > 2 ? `${digits.slice(0, 2)}.${digits.slice(2)}` : digits;
        syncHidden();
    });

    display.addEventListener('blur', syncHidden);
    syncHidden();
})();
</script>
