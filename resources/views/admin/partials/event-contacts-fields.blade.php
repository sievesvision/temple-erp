@php
    $contacts = $contacts ?? [];
    $suffix = $formSuffix ?? 'new';
    // A public contact list needs at least one entry to mean anything — start from a single
    // blank row rather than an empty list when the event has none configured yet.
    if (empty($contacts)) { $contacts = [['name' => '', 'phone' => '']]; }
@endphp
<div class="mb-3 event-contacts-field" data-max="8" data-min="1">
    <label class="form-label fw-semibold">Public Contact List</label>
    <div class="form-text mb-2">Shown on the event's public donation page. At least one contact is required.</div>
    <div class="event-contacts-rows" id="eventContactsRows_{{ $suffix }}">
        @foreach($contacts as $i => $contact)
        <div class="row g-2 align-items-center mb-2 contact-row" data-key="{{ $i }}">
            <div class="col-7">
                <input type="text" name="contact_name[{{ $i }}]" class="form-control form-control-sm rounded-3" placeholder="e.g. Senthil Kumaran" value="{{ $contact['name'] ?? '' }}">
            </div>
            <div class="col-4">
                <input type="text" name="contact_phone[{{ $i }}]" class="form-control form-control-sm rounded-3" placeholder="e.g. 0401 084 572" value="{{ $contact['phone'] ?? '' }}">
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-contact-row" title="Remove this contact"><i class="bi bi-x-lg"></i></button>
            </div>
        </div>
        @endforeach
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary add-contact-row" data-target="eventContactsRows_{{ $suffix }}">
        <i class="bi bi-plus-lg me-1"></i>Add Contact
    </button>
</div>

<template id="contactRowTemplate_{{ $suffix }}">
    <div class="row g-2 align-items-center mb-2 contact-row" data-key="__KEY__">
        <div class="col-7">
            <input type="text" name="contact_name[__KEY__]" class="form-control form-control-sm rounded-3" placeholder="e.g. Senthil Kumaran">
        </div>
        <div class="col-4">
            <input type="text" name="contact_phone[__KEY__]" class="form-control form-control-sm rounded-3" placeholder="e.g. 0401 084 572">
        </div>
        <div class="col-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-contact-row" title="Remove this contact"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
</template>

@once
<script>
(function () {
    let newContactCounter = 0;

    function refreshContactRowControls(wrap) {
        const field = wrap.closest('.event-contacts-field');
        const rows = wrap.querySelectorAll('.contact-row');
        const min = parseInt(field.dataset.min, 10) || 1;
        const max = parseInt(field.dataset.max, 10) || 8;
        rows.forEach(function (row) {
            const removeBtn = row.querySelector('.remove-contact-row');
            if (removeBtn) { removeBtn.disabled = rows.length <= min; }
        });
        const addBtn = field.querySelector('.add-contact-row');
        if (addBtn) { addBtn.disabled = rows.length >= max; }
    }

    document.addEventListener('click', function (e) {
        const addBtn = e.target.closest('.add-contact-row');
        if (addBtn) {
            const wrap = document.getElementById(addBtn.dataset.target);
            const field = wrap.closest('.event-contacts-field');
            const max = parseInt(field.dataset.max, 10) || 8;
            if (wrap.querySelectorAll('.contact-row').length >= max) { return; }
            const suffix = addBtn.dataset.target.replace('eventContactsRows_', '');
            const template = document.getElementById('contactRowTemplate_' + suffix);
            const key = 'new_' + (++newContactCounter) + '_' + Date.now();
            const html = template.innerHTML.replaceAll('__KEY__', key);
            wrap.insertAdjacentHTML('beforeend', html);
            refreshContactRowControls(wrap);
            return;
        }
        const removeBtn = e.target.closest('.remove-contact-row');
        if (removeBtn) {
            const wrap = removeBtn.closest('.event-contacts-rows');
            const field = wrap.closest('.event-contacts-field');
            const min = parseInt(field.dataset.min, 10) || 1;
            if (wrap.querySelectorAll('.contact-row').length <= min) { return; }
            removeBtn.closest('.contact-row').remove();
            refreshContactRowControls(wrap);
        }
    });

    document.querySelectorAll('.event-contacts-rows').forEach(refreshContactRowControls);
})();
</script>
@endonce
