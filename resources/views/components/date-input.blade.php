@php
    $fieldName = $name ?? 'date';
    $fieldId = $id ?? $fieldName;
    $rawValue = (string) ($value ?? '');
    $displayValue = '';

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawValue, $parts)) {
        $displayValue = $parts[3].'/'.$parts[2].'/'.$parts[1];
    }
@endphp

<div class="date-field">
    <input type="hidden" name="{{ $fieldName }}" id="{{ $fieldId }}" value="{{ $rawValue }}">
    <input
        class="form-control date-display"
        type="text"
        id="{{ $fieldId }}_display"
        value="{{ $displayValue }}"
        placeholder="dd/mm/yyyy"
        inputmode="numeric"
        maxlength="10"
        autocomplete="off"
        @if($required ?? false) required @endif
    >
    <span class="date-calendar-icon">▦</span>
    <input
        class="date-native-picker"
        type="date"
        id="{{ $fieldId }}_picker"
        value="{{ $rawValue }}"
        @if(!empty($min)) min="{{ $min }}" @endif
        @if(!empty($max)) max="{{ $max }}" @endif
        aria-label="Pilih tanggal"
        tabindex="-1"
    >
</div>

<script>
(() => {
    const hidden = document.getElementById(@json($fieldId));
    const display = document.getElementById(@json($fieldId.'_display'));
    const picker = document.getElementById(@json($fieldId.'_picker'));

    const toDisplay = (iso) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return '';
        const [year, month, day] = iso.split('-');
        return `${day}/${month}/${year}`;
    };

    const syncHidden = () => {
        const match = display.value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!match) {
            hidden.value = '';
            return;
        }

        const [, day, month, year] = match;
        const test = new Date(`${year}-${month}-${day}T00:00:00`);
        const valid = test.getFullYear() === Number(year)
            && test.getMonth() + 1 === Number(month)
            && test.getDate() === Number(day);

        hidden.value = valid ? `${year}-${month}-${day}` : '';
        if (valid) picker.value = hidden.value;
    };

    display.addEventListener('input', () => {
        const digits = display.value.replace(/\D/g, '').slice(0, 8);
        display.value = digits.length > 4
            ? `${digits.slice(0, 2)}/${digits.slice(2, 4)}/${digits.slice(4)}`
            : digits.length > 2
                ? `${digits.slice(0, 2)}/${digits.slice(2)}`
                : digits;
        syncHidden();
    });

    picker.addEventListener('change', () => {
        hidden.value = picker.value;
        display.value = toDisplay(picker.value);
    });
})();
</script>
