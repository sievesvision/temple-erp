@php $galleryImages = $galleryImages ?? []; @endphp
<div class="mb-3">
    <label class="form-label fw-semibold">Public Gallery Images (up to 6)</label>
    <div class="form-text mb-2">Shown as a gallery on the event's public donation page (e.g. tentative renovation plans). Same manually-typed path convention as the header/flyer/QR images above. Leave a row blank to skip it.</div>
    @for ($i = 1; $i <= 6; $i++)
        @php $image = $galleryImages[$i - 1] ?? ''; @endphp
        <div class="mb-2">
            <input type="text" name="gallery_image_{{ $i }}" class="form-control form-control-sm rounded-3" placeholder="e.g. images/events/kumabisekam1.jpeg" value="{{ $image }}">
        </div>
    @endfor
</div>
