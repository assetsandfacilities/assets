<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requisition extends Model
{
    use HasFactory;
    protected $fillable = [
        'requisition_no', 'user_id', 'department_id', 'branch', 'csf_no',
        'requested_by_name', 'checked_by_name', 'approved_by_name', 'status', 'purpose',
        'requested_at', 'approved_by', 'approved_at', 'asset_reviewed_by', 'asset_reviewed_at',
        'dean_approver_id', 'executive_approver_id', 'dean_approved_by', 'dean_approved_at', 'executive_approved_by', 'executive_approved_at',
        'finalized_at', 'rejection_reason'
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'asset_reviewed_at' => 'datetime',
        'dean_approved_at' => 'datetime',
        'executive_approved_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function items() { return $this->hasMany(RequisitionItem::class); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function assetReviewer() { return $this->belongsTo(User::class, 'asset_reviewed_by'); }
    public function selectedDeanApprover() { return $this->belongsTo(User::class, 'dean_approver_id'); }
    public function selectedExecutiveApprover() { return $this->belongsTo(User::class, 'executive_approver_id'); }
    public function deanApprover() { return $this->belongsTo(User::class, 'dean_approved_by'); }
    public function executiveApprover() { return $this->belongsTo(User::class, 'executive_approved_by'); }
    public function issuance() { return $this->hasOne(\App\Models\Issuance::class); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending_asset_management' => 'Pending - Asset Management',
            'pending_college_dean' => 'Pending - College Dean',
            'pending_executive_director' => 'Pending - Executive Director',
            'approved' => 'Approved',
            'partially_approved' => 'Partially Approved',
            'rejected' => 'Rejected',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function isAwaitingAssetManagement(): bool
    {
        return $this->status === 'pending_asset_management';
    }

    public function isAwaitingCollegeDean(): bool
    {
        return $this->status === 'pending_college_dean';
    }

    public function isAwaitingExecutiveDirector(): bool
    {
        return $this->status === 'pending_executive_director';
    }
}
