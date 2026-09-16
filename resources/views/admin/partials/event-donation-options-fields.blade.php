@php $options = $options ?? collect(); $suffix = $formSuffix ?? 'new'; @endphp
<div class="mb-3 donation-options-field" data-max="12" data-min="1">
    <label class="form-label fw-semibold">Donation Options</label>
    <div class="form-text mb-2">Leave amount blank for a "donor enters any amount" option. At least one option must remain once you've added any.</div>
    <div class="donation-options-rows" id="donationOptionsRows_{{ $suffix }}">
        @foreach($options as $opt)
        <div class="row g-2 align-items-center mb-2 option-row" data-key="{{ $opt->id }}">
            <input type="hidden" name="option_id[{{ $opt->id }}]" value="{{ $opt->id }}">
            <div class="col-5">
                <input type="text" name="option_label[{{ $opt->id }}]" class="form-control form-control-sm rounded-3" placeholder="e.g. Sponsorship for a Conch" value="{{ $opt->label }}">
            </div>
            <div class="col-3">
                <input type="number" step="0.01" min="0" name="option_amount[{{ $opt->id }}]" class="form-control form-control-sm rounded-3" placeholder="Any amount" value="{{ $opt->amount }}">
            </div>
            <div class="col-3 form-check ms-2">
                <input type="checkbox" name="option_allow_qty[{{ $opt->id }}]" id="option_allow_qty_{{ $opt->id }}_{{ $suffix }}" class="form-check-input" value="1" {{ $opt->allow_quantity ? 'checked' : '' }}>
                <label class="form-check-label small" for="option_allow_qty_{{ $opt->id }}_{{ $suffix }}">Allow quantity</label>
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-option-row" title="Remove this option"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary add-option-row" data-target="donationOptionsRows_{{ $suffix }}">
        <i class="bi bi-plus-lg me-1"></i>Add Option
    </button>
</div>

<template id="donationOptionRowTemplate_{{ $suffix }}">
    <div class="row g-2 align-items-center mb-2 option-row" data-key="__KEY__">
        <input type="hidden" name="option_id[__KEY__]" value="">
        <div class="col-5">
            <input type="text" name="option_label[__KEY__]" class="form-control form-control-sm rounded-3" placeholder="e.g. Sponsorship for a Conch">
        </div>
        <div class="col-3">
            <input type="number" step="0.01" min="0" name="option_amount[__KEY__]" class="form-control form-control-sm rounded-3" placeholder="Any amount">
        </div>
        <div class="col-3 form-check ms-2">
            <input type="checkbox" name="option_allow_qty[__KEY__]" id="option_allow_qty___KEY___{{ $suffix }}" class="form-check-input" value="1">
            <label class="form-check-label small" for="option_allow_qty___KEY___{{ $suffix }}">Allow quantity</label>
        </div>
        <div class="col-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-option-row" title="Remove this option"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</template>

@once
<script>
(function () {
    let newRowCounter = 0;

    function refreshRowControls(wrap) {
        const field = wrap.closest('.donation-options-field');
        const rows = wrap.querySelectorAll('.option-row');
        const min = parseInt(field.dataset.min, 10) || 1;
        const max = parseInt(field.dataset.max, 10) || 12;
        rows.forEach(function (row) {
            const removeBtn = row.querySelector('.remove-option-row');
            if (removeBtn) { removeBtn.disabled = rows.length <= min; }
        });
        const addBtn = field.querySelector('.add-option-row');
        if (addBtn) { addBtn.disabled = rows.length >= max; }
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.add-option-row');
        if (addBtn) {
            const wrap = document.getElementById(addBtn.dataset.target);
            const field = wrap.closest('.donation-options-field');
            const max = parseInt(field.dataset.max, 10) || 12;
            if (wrap.querySelectorAll('.option-row').length >= max) { return; }
            const suffix = addBtn.dataset.target.replace('donationOptionsRows_', '');
            const template = document.getElementById('donationOptionRowTemplate_' + suffix);
            const key = 'new_' + (++newRowCounter) + '_' + Date.now();
            const html = template.innerHTML.replaceAll('__KEY__', key);
            wrap.insertAdjacentHTML('beforeend', html);
            refreshRowControls(wrap);
            return;
        }
        const removeBtn = e.target.closest('.remove-option-row');
        if (removeBtn) {
            const wrap = removeBtn.closest('.donation-options-rows');
            const field = wrap.closest('.donation-options-field');
            const min = parseInt(field.dataset.min, 10) || 1;
            if (wrap.querySelectorAll('.option-row').length <= min) { return; }
            removeBtn.closest('.option-row').remove();
            refreshRowControls(wrap);
        }
    });

    document.querySelectorAll('.donation-options-rows').forEach(refreshRowControls);
})();
</script>
@endonce
