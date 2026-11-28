<div class="p-3 border rounded" style="background:var(--bs-body-bg);">
    <label class="form-label small fw-semibold mb-2">
        <i class="bi {{ $icon }} me-1"></i> {{ $label }}
    </label>
    <div class="d-flex align-items-center gap-2">
        <input type="color" name="{{ $name }}" id="{{ $name }}"
               class="form-control form-control-color"
               value="{{ $value }}"
               data-hex-target="{{ $name }}_hex"
               style="width:50px;height:34px;cursor:pointer;">
        <input type="text" id="{{ $name }}_hex" class="form-control form-control-sm font-monospace"
               value="{{ $value }}" placeholder="#000000" maxlength="7" style="max-width:100px;">
    </div>
</div>
