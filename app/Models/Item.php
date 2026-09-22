<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_id','item_code','name','item_type','description','specifications','quantity',
        'unit','unit_price','brand','low_stock_threshold','availability_status','qr_value','room_assigned','floor','image_path','is_active',
        'acquisition_date','assigned_department','asset_type_name',
        'floor_id','room_id','assigned_department_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unit_price' => 'decimal:2',
        'acquisition_date' => 'date',
    ];

    protected $appends = [
        'asset_tag_id',
        'asset_description',
        'date_acquired_label',
        'department_label',
        'asset_type_label',
    ];

    public function category() { return $this->belongsTo(ItemCategory::class, 'category_id'); }
    public function requisitionItems(): HasMany { return $this->hasMany(RequisitionItem::class); }
    public function floorRef() { return $this->belongsTo(Floor::class, 'floor_id'); }
    public function room() { return $this->belongsTo(Room::class, 'room_id'); }
    public function assignedDepartment() { return $this->belongsTo(Department::class, 'assigned_department_id'); }


    public function getAssetTagIdAttribute(): string
    {
        return (string) ($this->item_code ?: $this->qr_value ?: ('ITEM-' . $this->id));
    }

    public function getAssetDescriptionAttribute(): string
    {
        return (string) ($this->description ?: $this->name);
    }

    public function getDateAcquiredLabelAttribute(): string
    {
        $date = $this->acquisition_date ?: $this->created_at;

        if ($date instanceof \Illuminate\Support\Carbon) {
            return $date->format('d-M-y');
        }

        return $date ? \Illuminate\Support\Carbon::parse($date)->format('d-M-y') : 'N/A';
    }

    public function getDepartmentLabelAttribute(): string
    {
        if (!empty($this->assigned_department)) {
            return (string) $this->assigned_department;
        }

        $latestRequisitionItem = $this->requisitionItems()->with('requisition.department')->latest('id')->first();
        $departmentName = $latestRequisitionItem?->requisition?->department?->name;

        if (!empty($departmentName)) {
            return (string) $departmentName;
        }

        return 'Asset Management Office';
    }

    public function getAssetTypeLabelAttribute(): string
    {
        if (!empty($this->asset_type_name)) {
            return (string) $this->asset_type_name;
        }

        if (!empty($this->category?->name)) {
            return (string) $this->category->name;
        }

        return $this->item_type === 'CAPEX' ? 'CAPEX Asset' : 'OPEX Item';
    }

    public function isLowStock(): bool
    {
        return $this->item_type === 'OPEX' && $this->quantity <= $this->low_stock_threshold;
    }

    public function getDisplayImageAttribute(): string
    {
        if (!empty($this->image_path)) {
            if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://') || str_starts_with($this->image_path, 'data:image/')) {
                return $this->image_path;
            }

            return asset($this->image_path);
        }

        $label = htmlspecialchars($this->name ?: 'Item', ENT_QUOTES, 'UTF-8');
        $type = htmlspecialchars($this->item_type ?: 'ITEM', ENT_QUOTES, 'UTF-8');
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='400' viewBox='0 0 600 400'>"
            . "<rect width='600' height='400' fill='#e5e7eb'/>"
            . "<rect x='40' y='40' width='520' height='320' rx='24' fill='#d1d5db' stroke='#9ca3af'/>"
            . "<text x='300' y='150' text-anchor='middle' font-size='34' font-family='Arial' fill='#374151'>".$type."</text>"
            . "<text x='300' y='220' text-anchor='middle' font-size='26' font-family='Arial' fill='#111827'>".$label."</text>"
            . "<text x='300' y='280' text-anchor='middle' font-size='18' font-family='Arial' fill='#4b5563'>Asset Management Office</text>"
            . "</svg>";

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    public function isOutOfStock(): bool
    {
        return $this->availability_status === 'Out of Stock' || (int) $this->quantity <= 0;
    }

    public function isLimitedStock(): bool
    {
        return $this->availability_status === 'Limited Stock' || ($this->item_type === 'OPEX' && (int) $this->quantity > 0 && (int) $this->quantity <= (int) $this->low_stock_threshold);
    }
}
