@php $contacts = $contacts ?? []; @endphp
<div class="mb-3">
    <label class="form-label fw-semibold">Public Contact List (up to 8)</label>
    <div class="form-text mb-2">Shown on the event's public donation page. Leave a row's name blank to skip it.</div>
    @for ($i = 1; $i <= 8; $i++)
        @php $contact = $contacts[$i - 1] ?? null; @endphp
        <div class="row g-2 align-items-center mb-2">
            <div class="col-7">
                <input type="text" name="contact_name_{{ $i }}" class="form-control form-control-sm rounded-3" placeholder="e.g. Senthil Kumaran" value="{{ $contact['name'] ?? '' }}">
            </div>
            <div class="col-5">
                <input type="text" name="contact_phone_{{ $i }}" class="form-control form-control-sm rounded-3" placeholder="e.g. 0401 084 572" value="{{ $contact['phone'] ?? '' }}">
            </div>
        </div>
    @endfor
</div>
