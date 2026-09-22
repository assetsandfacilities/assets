<div class="row g-3">
  <div class="col-md-4"><label class="form-label">Venue Code</label><input name="code" class="form-control" value="{{ old('code', $facility->code ?? '') }}" required></div>
  <div class="col-md-8"><label class="form-label">Venue Name</label><input name="name" class="form-control" value="{{ old('name', $facility->name ?? '') }}" required></div>
  <div class="col-md-6"><label class="form-label">Location</label><input name="location" class="form-control" value="{{ old('location', $facility->location ?? '') }}"></div>
  <div class="col-md-3"><label class="form-label">Reference Latitude <span class="tiny text-muted">(optional)</span></label><input name="latitude" class="form-control" value="{{ old('latitude', $facility->latitude ?? '') }}" placeholder="e.g. 15.1739"></div>
  <div class="col-md-3"><label class="form-label">Reference Longitude <span class="tiny text-muted">(optional)</span></label><input name="longitude" class="form-control" value="{{ old('longitude', $facility->longitude ?? '') }}" placeholder="e.g. 120.5670"></div>
  <div class="col-12 tiny text-muted">If set, the mobile app verifies asset scans near this room using real GPS distance instead of just comparing room names.</div>
  <div class="col-md-6"><label class="form-label">Capacity</label><input type="number" min="0" name="capacity" class="form-control" value="{{ old('capacity', $facility->capacity ?? 0) }}"></div>
  <div class="col-12"><label class="form-label">Available Resources</label><textarea name="resources" class="form-control" rows="3" placeholder="Projector, chairs, sound system, etc.">{{ old('resources', $facility->resources ?? '') }}</textarea></div>
  <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" value="1" name="is_active" id="is_active" @checked(old('is_active', $facility->is_active ?? true))><label class="form-check-label" for="is_active">Active — selectable on the Reservation Request form</label></div>
</div>
