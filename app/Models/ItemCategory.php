<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use HasFactory;
    protected $table = 'item_categories';
    protected $fillable = ['name', 'description', 'item_type'];

    public function items()
    {
        return $this->hasMany(Item::class, 'category_id');
    }

    public function assetTypes()
    {
        return $this->hasMany(AssetType::class, 'item_category_id');
    }
}
