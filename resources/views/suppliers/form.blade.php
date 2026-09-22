<div class="row g-3">
<div class="col-md-6"><label class="form-label">Supplier Name</label><input name="name" class="form-control" value="{{ old('name',$supplier->name ?? '') }}" required></div>
<div class="col-md-6"><label class="form-label">Contact Person</label><input name="contact_person" class="form-control" value="{{ old('contact_person',$supplier->contact_person ?? '') }}"></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email',$supplier->email ?? '') }}"></div>
<div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" value="{{ old('phone',$supplier->phone ?? '') }}"></div>
<div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control">{{ old('address',$supplier->address ?? '') }}</textarea></div>
</div>
