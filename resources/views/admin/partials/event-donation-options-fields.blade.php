@php $options = $options ?? collect(); @endphp
<div class="mb-3">
    <label class="form-label fw-semibold">Donation Options (up to 5)</label>
    <div class="form-text mb-2">Leave a row's label blank to skip it. Leave amount blank for a "donor enters any amount" option.</div>
    @for ($i = 1; $i <= 5; $i++)
        @php $opt = $options->get($i - 1); @endphp
        <div class="row g-2 align-items-center mb-2">
            <div class="col-5">
                <input type="text" name="option_label_{{ $i }}" class="form-control form-control-sm rounded-3" placeholder="e.g. Sponsorship for a Conch" value="{{ $opt->label ?? '' }}">
            </div>
            <div class="col-3">
                <input type="number" step="0.01" min="0" name="option_amount_{{ $i }}" class="form-control form-control-sm rounded-3" placeholder="Any amount" value="{{ $opt->amount ?? '' }}">
            </div>
            <div class="col-4 form-check ms-2">
                <input type="checkbox" name="option_allow_qty_{{ $i }}" id="option_allow_qty_{{ $i }}_{{ $formSuffix ?? 'new' }}" class="form-check-input" value="1" {{ ($opt->allow_quantity ?? false) ? 'checked' : '' }}>
                <label class="form-check-label small" for="option_allow_qty_{{ $i }}_{{ $formSuffix ?? 'new' }}">Allow quantity</label>
            </div>
        </div>
    @endfor
</div>
