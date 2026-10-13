@php $prefix = $prefix ?? ''; @endphp
<div class="mb-3">
    <label class="form-label fw-bold">Pooja Name</label>
    <input type="text" name="pooja_name" id="{{ $prefix }}pooja_name" class="form-control" value="{{ $pooja->pooja_name ?? '' }}" required>
</div>
<div class="mb-3">
    <label class="form-label fw-bold">Category</label>
    <input type="text" name="category" id="{{ $prefix }}category" class="form-control" list="poojaCategoryOptions" value="{{ $pooja->category ?? '' }}" placeholder="e.g. Sponsored Poojas, Festivals...">
</div>
<div class="mb-3">
    <label class="form-label fw-bold">Description / Notes</label>
    <textarea name="description" id="{{ $prefix }}description" class="form-control" rows="3" placeholder="What devotees should bring, prasadam entitlement, ordering options, etc.">{{ $pooja->description ?? '' }}</textarea>
</div>
<div class="row g-3 mb-3">
    <div class="col-md-6">
        <label class="form-label fw-bold">Fee</label>
        <input type="number" step="0.01" min="0" name="pooja_fee" id="{{ $prefix }}pooja_fee" class="form-control" value="{{ $pooja->pooja_fee ?? '' }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label fw-bold">Typical Duration</label>
        <input type="text" name="duration" id="{{ $prefix }}duration" class="form-control" value="{{ $pooja->duration ?? '' }}" placeholder="e.g. 30 (minutes)">
    </div>
</div>
<div class="row g-3 align-items-center">
    <div class="col-md-6">
        <label class="form-label fw-bold">Status</label>
        <select name="status" id="{{ $prefix }}status" class="form-select">
            <option value="Active" {{ ($pooja->status ?? 'Active') === 'Active' ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ ($pooja->status ?? '') === 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    <div class="col-md-6">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" name="online_allowed" id="{{ $prefix }}online_allowed" value="1" {{ ($pooja->online_allowed ?? false) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="{{ $prefix }}online_allowed">Allow online booking (ships prasadam)</label>
        </div>
    </div>
</div>
